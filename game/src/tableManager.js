// tableManager.js — authoritative in-memory game tables.
// Money flows through the backend only; this module handles the domino state
// machine and emits lifecycle events for the socket layer to forward.
const domino = require('./domino');

const STATUS = {
  WAITING: 'waiting',
  PLAYING: 'playing',
  FINISHED: 'finished',
};

class Seat {
  constructor(userId, name, token) {
    this.userId = userId;
    this.name = name;
    this.token = token;
    this.hand = [];
    this.score = 0;
    this.connected = true;
  }
}

class Table {
  /**
   * @param {object} init
   * @param {number} init.gameId        backend games.id
   * @param {string} init.matchId       backend matches.id (uuid)
   * @param {number} init.minBet        entry stake $
   * @param {number} init.maxPlayers
   * @param {string} init.hostToken     a valid game token of a seated player (for API calls)
   */
  constructor({ gameId, matchId, minBet, maxPlayers, hostToken, hostName }) {
    this.gameId = gameId;
    this.matchId = matchId;
    this.minBet = minBet;
    this.maxPlayers = maxPlayers;
    this.hostToken = hostToken;
    this.hostName = hostName;
    this.status = STATUS.WAITING;
    this.seats = new Map(); // userId -> Seat
    this.order = []; // userId insertion order (also turn order)
    this.board = []; // oriented tiles
    this.turnIdx = 0;
    this.lastPlayUserId = null;
    this.consecutivePasses = 0;
    this.winnerId = null;
  }

  get room() {
    return `table:${this.matchId}`;
  }

  addPlayer(userId, name, token) {
    if (this.seats.has(userId)) return false;
    if (this.status !== STATUS.WAITING) return false;
    if (this.seats.size >= this.maxPlayers) return false;
    this.seats.set(userId, new Seat(userId, name, token));
    this.order.push(userId);
    return true;
  }

  removePlayer(userId) {
    if (!this.seats.has(userId)) return false;
    this.seats.delete(userId);
    this.order = this.order.filter((id) => id !== userId);
    return true;
  }

  size() {
    return this.seats.size;
  }

  canStart() {
    return this.status === STATUS.WAITING && this.seats.size >= 2 && this.seats.size <= this.maxPlayers;
  }

  start() {
    if (!this.canStart()) return false;
    this.status = STATUS.PLAYING;
    this.dealNewHand();
    return true;
  }

  dealNewHand() {
    const hands = domino.deal(this.seats.size);
    this.order.forEach((uid, i) => {
      this.seats.get(uid).hand = hands[i];
    });
    this.board = [];
    const first = domino.pickStarter(hands);
    this.turnIdx = first;
    this.lastPlayUserId = null;
    this.consecutivePasses = 0;
  }

  currentTurnUserId() {
    if (this.order.length === 0) return null;
    return this.order[this.turnIdx % this.order.length];
  }

  isTurn(userId) {
    return this.currentTurnUserId() === userId;
  }

  legalMovesFor(userId) {
    const seat = this.seats.get(userId);
    if (!seat) return [];
    return domino.legalMoves(this.board, seat.hand);
  }

  /**
   * Try to play a tile. Returns {ok:true} | {ok:false, reason}.
   * Handles hand-win and match-win; returns events to emit.
   */
  playTile(userId, tile) {
    if (this.status !== STATUS.PLAYING) return { ok: false, reason: 'La partida no está en curso.' };
    if (!this.isTurn(userId)) return { ok: false, reason: 'No es tu turno.' };

    const seat = this.seats.get(userId);
    const moves = domino.legalMoves(this.board, seat.hand);
    const move = moves.find((m) => m.tile[0] === tile[0] && m.tile[1] === tile[1]);
    if (!move) return { ok: false, reason: 'Esa ficha no se puede jugar.' };

    const newBoard = domino.placeTile(this.board, tile, move.side);
    if (!newBoard) return { ok: false, reason: 'Jugada ilegal.' };

    const newHand = domino.removeTile(seat.hand, tile);
    seat.hand = newHand;
    this.board = newBoard;
    this.lastPlayUserId = userId;
    this.consecutivePasses = 0;
    this.turnIdx = (this.turnIdx + 1) % this.order.length;

    if (newHand.length === 0) return this._finishHand(userId, `🀄 ${seat.name} se quedó sin fichas!`);
    return { ok: true };
  }

  pass(userId) {
    if (this.status !== STATUS.PLAYING) return { ok: false, reason: 'La partida no está en curso.' };
    if (!this.isTurn(userId)) return { ok: false, reason: 'No es tu turno.' };

    const seat = this.seats.get(userId);
    if (domino.legalMoves(this.board, seat.hand).length > 0) {
      return { ok: false, reason: 'Tienes fichas jugables; no puedes pasar.' };
    }

    this.consecutivePasses += 1;
    this.turnIdx = (this.turnIdx + 1) % this.order.length;

    // Board closed: nobody can play → last player to play wins the hand.
    if (this.consecutivePasses >= this.order.length) {
      return this._finishHand(this.lastPlayUserId, `🔒 Mesa cerrada — ${this.seats.get(this.lastPlayUserId)?.name ?? 'nadie'} gana la mano`);
    }
    return { ok: true };
  }

  resign(userId) {
    const seat = this.seats.get(userId);
    const name = seat?.name ?? userId;
    this.removePlayer(userId);

    // Only one remaining → they win the match by default (backend settles).
    if (this.status === STATUS.PLAYING && this.order.length === 1) {
      this.winnerId = this.order[0];
      this.status = STATUS.FINISHED;
      return { ended: true, winnerId: this.winnerId, name: this.seats.get(this.winnerId)?.name ?? name };
    }
    // Nobody left before start → refund handled by socket layer.
    if (this.order.length === 0) {
      this.status = STATUS.FINISHED;
      return { ended: false, empty: true, name };
    }
    if (this.lastPlayUserId === userId) {
      this.lastPlayUserId = this.order[0];
    }
    return { ended: false };
  }

  /** Mark disconnected; auto-pass if it's their turn; finish if only one stays. */
  handleDisconnect(userId) {
    const seat = this.seats.get(userId);
    if (!seat) return { gone: true };
    seat.connected = false;

    if (this.status === STATUS.PLAYING) {
      if (this.isTurn(userId)) {
        this.consecutivePasses += 1;
        this.turnIdx = (this.turnIdx + 1) % this.order.length;
        if (this.consecutivePasses >= this.order.length) {
          this._finishHand(this.lastPlayUserId, '🔒 Cierre por desconexiones');
        }
      }
      const connected = this.order.filter((id) => this.seats.get(id).connected);
      if (connected.length <= 1) {
        this.winnerId = connected[0];
        this.status = STATUS.FINISHED;
        return { finished: true, winnerId: this.winnerId };
      }
    }
    return { gone: true };
  }

  _finishHand(winnerUserId, label) {
    const winner = this.seats.get(winnerUserId);
    if (!winner) {
      // nobody played yet and board closed (edge): pick first seat
      const ids = [...this.seats.keys()];
      return { ok: true, handWinner: ids.length ? ids[0] : null, label: 'Mano inválida' };
    }
    let points = 0;
    for (const uid of this.order) {
      if (uid !== winnerUserId) points += domino.handPoints(this.seats.get(uid).hand);
    }
    winner.score += points;

    this.board = [];

    if (winner.score >= domino.TARGET) {
      this.status = STATUS.FINISHED;
      this.winnerId = winnerUserId;
      return { ok: true, matchWinner: winnerUserId, handWinner: winnerUserId, points, label, scores: this.scores() };
    }
    this.dealNewHand();
    return { ok: true, handWinner: winnerUserId, points, label, scores: this.scores() };
  }

  scores() {
    const out = {};
    for (const [uid, seat] of this.seats) out[uid] = seat.score;
    return out;
  }

  /** Public state for a given viewer (private hand included only for the owner). */
  stateFor(userId) {
    const publicSeats = this.order.map((uid) => {
      const s = this.seats.get(uid);
      return {
        user_id: uid,
        name: s.name,
        hand_size: s.hand.length,
        score: s.score,
        connected: s.connected,
        is_turn: this.isTurn(uid),
      };
    });
    const state = {
      match_id: this.matchId,
      game_id: this.gameId,
      min_bet: this.minBet,
      status: this.status,
      players: publicSeats,
      board: this.board,
      current_turn: this.currentTurnUserId(),
      last_play: this.lastPlayUserId,
      scores: this.scores(),
      winner_id: this.winnerId,
    };
    const seat = this.seats.get(userId);
    if (seat) {
      state.hand = seat.hand;
      state.legal_moves = this.isTurn(userId) ? domino.legalMoves(this.board, seat.hand) : [];
    }
    return state;
  }
}

module.exports = { Table, STATUS };