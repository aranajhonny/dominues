// socketHandlers.js — socket.io wiring: auth, lobby, tables, chat.
const backendClient = require('./backendClient');
const { Table } = require('./tableManager');
const domino = require('./domino');
const registry = require('./registry');

const { tables, byGame, userIdToMatch } = registry;
const lobbyUsers = new Map(); // socketId -> {name, user_id}
const socketsByMatch = new Map(); // matchId -> Set<socketId>
let gamesCache = { at: 0, data: [] };
const GAME_CACHE_TTL = 60_000;

async function getGames() {
  if (Date.now() - gamesCache.at > GAME_CACHE_TTL) {
    try {
      const list = await backendClient.listGames();
      gamesCache = { at: Date.now(), data: list.games || [] };
    } catch (_) {
      /* keep stale cache */
    }
  }
  return gamesCache.data;
}

function lobbyState() {
  const users = [...lobbyUsers.values()].map((u) => ({ id: u.user_id, name: u.name }));
  const lobbyGames = gamesCache.data.map((g) => {
    const activeMatchId = byGame.get(g.id);
    const active = activeMatchId ? tables.get(activeMatchId) : null;
    return {
      id: g.id,
      name: g.name,
      mode: g.mode,
      min_bet: g.min_bet,
      max_players: g.max_players,
      players_count: active ? active.size() : 0,
      in_play: active ? active.status : 'waiting',
    };
  });
  return { users, games: lobbyGames };
}

function emitToTable(io, matchId, event, payload) {
  io.to(`table:${matchId}`).emit(event, payload);
}

/** Broadcast the full public state; every seated socket gets its own private hand. */
function broadcastTable(io, matchId) {
  const ids = socketsByMatch.get(matchId) || new Set();
  for (const id of ids) {
    const socket = io.sockets.sockets.get(id);
    if (!socket) continue;
    const userId = socket.data?.user?.id;
    const table = tables.get(matchId);
    if (!table) continue;
    socket.emit('table_state', table.stateFor(userId));
  }
}

function joinTableRoom(socket, matchId) {
  socket.join(`table:${matchId}`);
  if (!socketsByMatch.has(matchId)) socketsByMatch.set(matchId, new Set());
  socketsByMatch.get(matchId).add(socket.id);
}

function leaveTableRoom(socket, matchId) {
  socket.leave(`table:${matchId}`);
  const set = socketsByMatch.get(matchId);
  if (set) {
    set.delete(socket.id);
    if (set.size === 0) socketsByMatch.delete(matchId);
  }
}

function removeTable(matchId) {
  const table = tables.get(matchId);
  if (table) byGame.delete(table.gameId);
  tables.delete(matchId);
  socketsByMatch.delete(matchId);
  for (const [uid, mid] of userIdToMatch) if (mid === matchId) userIdToMatch.delete(uid);
}

async function settleAndClose(io, matchId, table, winnerUserId) {
  try {
    const winnerSeat = [...table.seats.values()].find((s) => s.userId === winnerUserId);
    const token = winnerSeat?.token || table.hostToken;
    const res = await backendClient.result(token, matchId, winnerUserId);
    emitToTable(io, matchId, 'match_finished', {
      winner_name: winnerSeat?.name || String(winnerUserId),
      winner_id: winnerUserId,
      prize: res.prize,
      pot: res.pot,
      fee: res.fee,
      scores: table.scores(),
    });
  } catch (e) {
    emitToTable(io, matchId, 'error', { message: `No se pudo liquidar la partida: ${e.message}` });
  } finally {
    io.in(`table:${matchId}`).socketsLeave(`table:${matchId}`);
    removeTable(matchId);
  }
}

module.exports = function registerSocketHandlers(io) {
  io.on('connection', async (socket) => {
    const token = socket.handshake.query?.token;
    if (!token) {
      socket.emit('auth_error', { message: 'Token requerido' });
      socket.disconnect(true);
      return;
    }

    let user;
    try {
      const v = await backendClient.validate(token);
      if (!v.valid) throw new Error('invalid');
      user = v.user;
    } catch (e) {
      socket.emit('auth_error', { message: 'Token inválido o expirado.' });
      socket.disconnect(true);
      return;
    }

    socket.data = { token, user };
    socket.join('lobby');
    lobbyUsers.set(socket.id, { name: user.name, user_id: user.id });

    // Tell the client who they are (id drives private-hand rendering + myUserId).
    socket.emit('user_ready', { id: user.id, name: user.name, balance: user.balance });

    const refreshLobby = async () => {
      await getGames();
      io.to('lobby').emit('lobby_state', lobbyState());
    };
    refreshLobby().catch(() => {});
    socket.on('refresh_lobby', () => refreshLobby().catch(() => {}));

    // ----------------------------------------------------------- join_table
    socket.on('join_table', async (payload) => {
      const gameId = Number(payload?.game_id);
      if (!gameId) return socket.emit('error', { message: 'Juego inválido.' });

      let matchId = byGame.get(gameId);
      let table = matchId ? tables.get(matchId) : null;

      if (table && table.status === 'playing' && !table.seats.has(user.id)) {
        return socket.emit('join_error', { message: 'La mesa ya está en partida.' });
      }

      try {
        const res = await backendClient.join(token, gameId);
        if (!res.ok) return socket.emit('join_error', { message: res.error || 'No se pudo entrar.' });

        // Double-check after the await: another concurrent join may have
        // already created the Table for this match (single-threaded JS makes
        // this check race-free).
        const existing = byGame.get(gameId) && tables.get(byGame.get(gameId));
        if (existing) {
          table = existing;
          matchId = existing.matchId;
        } else if (!table) {
          table = new Table({
            gameId,
            matchId: res.match_id,
            minBet: Number(res.stake || 0),
            maxPlayers: 4,
            hostToken: token,
            hostName: user.name,
          });
          tables.set(res.match_id, table);
          byGame.set(gameId, res.match_id);
          matchId = res.match_id;
        }

        table.addPlayer(user.id, user.name, token);
        userIdToMatch.set(user.id, table.matchId);
        socket.leave('lobby');
        joinTableRoom(socket, table.matchId);

        // Re-seat the bits that need to be known to handle disconnects
        socket.data.matchId = table.matchId;
        socket.data.gameId = gameId;

        broadcastTable(io, table.matchId);
        refreshLobby().catch(() => {});
      } catch (e) {
        socket.emit('join_error', { message: e.message });
      }
    });

    // ---------------------------------------------------------- start match
    socket.on('start', () => {
      const table = tables.get(socket.data.matchId);
      if (!table) return socket.emit('error', { message: 'No estás en una mesa.' });
      if (!table.seats.has(user.id)) return socket.emit('error', { message: 'Debes estar sentado en la mesa.' });
      if (!table.start()) return socket.emit('error', { message: 'Se necesitan al menos 2 jugadores.' });
      broadcastTable(io, table.matchId);
      refreshLobby().catch(() => {});
    });

    // ------------------------------------------------------------ play_tile
    socket.on('play_tile', (payload) => {
      const table = tables.get(socket.data.matchId);
      if (!table) return socket.emit('error', { message: 'No estás en una mesa.' });
      const result = table.playTile(user.id, payload?.tile);
      if (!result.ok) return socket.emit('invalid_move', { reason: result.reason });

      broadcastTable(io, table.matchId);

      if (result.handWinner) {
        const w = table.seats.get(result.handWinner);
        emitToTable(io, table.matchId, 'hand_winner', {
          winner_name: w?.name || String(result.handWinner),
          winner_id: result.handWinner,
          points: result.points,
          label: result.label,
          scores: result.scores,
        });
        if (result.matchWinner) {
          setTimeout(() => settleAndClose(io, table.matchId, table, result.matchWinner), 250);
        } else {
          setTimeout(() => broadcastTable(io, table.matchId), 1200);
        }
      }
    });

    // ----------------------------------------------------------------- pass
    socket.on('pass', () => {
      const table = tables.get(socket.data.matchId);
      if (!table) return socket.emit('error', { message: 'No estás en una mesa.' });
      const result = table.pass(user.id);
      if (!result.ok) return socket.emit('invalid_move', { reason: result.reason });

      broadcastTable(io, table.matchId);
      if (result.handWinner) {
        const w = table.seats.get(result.handWinner);
        emitToTable(io, table.matchId, 'hand_winner', {
          winner_name: w?.name || String(result.handWinner),
          winner_id: result.handWinner,
          points: result.points,
          label: result.label,
          scores: result.scores,
        });
        if (result.matchWinner) {
          setTimeout(() => settleAndClose(io, table.matchId, table, result.matchWinner), 250);
        } else {
          setTimeout(() => broadcastTable(io, table.matchId), 1200);
        }
      }
    });

    // ------------------------------------------------------------------ resign
    socket.on('resign', async () => {
      const table = tables.get(socket.data.matchId);
      if (!table) return socket.emit('error', { message: 'No estás en una mesa.' });

      if (table.status === 'waiting') {
        try {
          await backendClient.refund(table.seats.get(user.id)?.token || token, table.gameId);
        } catch (_) { /* still remove locally */ }
      }

      const r = table.resign(user.id);
      socket.data.matchId = null;
      leaveTableRoom(socket, table.matchId);
      socket.join('lobby');

      if (r.empty) {
        removeTable(table.matchId);
        refreshLobby().catch(() => {});
        return;
      }
      if (r.ended && r.winnerId) {
        socket.join('lobby');
        await settleAndClose(io, table.matchId, table, r.winnerId);
        refreshLobby().catch(() => {});
        return;
      }
      broadcastTable(io, table.matchId);
      refreshLobby().catch(() => {});
    });

    // ------------------------------------------------------------------- chat
    socket.on('chat', (payload) => {
      const table = tables.get(socket.data.matchId);
      const msg = String(payload?.message || '').slice(0, 300);
      if (!msg) return;
      if (table) {
        emitToTable(io, table.matchId, 'chat', { user_id: user.id, name: user.name, message: msg });
      } else {
        io.to('lobby').emit('chat', { user_id: user.id, name: user.name, message: msg });
      }
    });

    // -------------------------------------------------------------- disconnect
    socket.on('disconnect', async () => {
      lobbyUsers.delete(socket.id);
      const matchId = socket.data.matchId;
      if (!matchId) return;
      const table = tables.get(matchId);
      if (!table) return;
      leaveTableRoom(socket, matchId);

      const seat = table.seats.get(user.id);

      if (table.status === 'waiting' && seat) {
        try {
          await backendClient.refund(seat.token, table.gameId);
        } catch (_) { /* best effort */ }
        table.removePlayer(user.id);
        broadcastTable(io, matchId);
        if (table.size() === 0) removeTable(matchId);
        refreshLobby().catch(() => {});
        return;
      }

      const h = table.handleDisconnect(user.id);
      if (h.finished) {
        await settleAndClose(io, matchId, table, h.winnerId);
        refreshLobby().catch(() => {});
        return;
      }
      broadcastTable(io, matchId);
      refreshLobby().catch(() => {});
    });
  });
};