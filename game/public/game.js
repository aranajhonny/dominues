// game.js — Dominues game client (lobby + table + chat).
const params = new URLSearchParams(location.search);
const token = params.get('token');

const $ = (id) => document.getElementById(id);
const state = { user: null, balance: '0', matchId: null, table: null, lastState: null, myUserId: null };

function toast(text, kind = 'info') {
  const el = document.createElement('div');
  el.className = `toast align-items-center text-bg-${kind === 'error' ? 'danger' : kind === 'ok' ? 'success' : 'dark'} border-0 show`;
  el.innerHTML = `<div class="d-flex"><div class="toast-body">${text}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
  $('toast').appendChild(el);
  setTimeout(() => el.remove(), 5000);
}

function tileEl([a, b], { private: isPrivate = false, playable = false, played = false, empty = false } = {}) {
  const d = document.createElement('div');
  if (empty) { d.className = 'tile empty'; d.textContent = '·'; return d; }
  const cls = ['tile'];
  if (a === b) cls.push('double'); else cls.push('horizontal');
  if (isPrivate) cls.push('private');
  if (playable) cls.push('playable');
  if (played) cls.push('played');
  d.className = cls.join(' ');
  const [x, y] = a <= b ? [a, b] : [b, a];
  if (a === b) {
    d.innerHTML = `<div class="pip-half">${x}</div><div class="pip-half">${y}</div>`;
    d.style.flexDirection = 'column';
  } else {
    d.innerHTML = `<div class="pip-half text-end pe-1">${x}</div><div class="pip-half ps-1">${y}</div>`;
  }
  if (playable) d.dataset.tile = JSON.stringify([a, b]);
  return d;
}

function showView(name) {
  $('view-lobby').classList.toggle('hidden', name !== 'lobby');
  $('view-table').classList.toggle('hidden', name !== 'table');
}

function renderLobby(ls) {
  const box = $('lobby-games');
  box.innerHTML = '';
  if (!ls || !ls.games || ls.games.length === 0) {
    box.innerHTML = '<div class="text-secondary small">No hay juegos activos.</div>';
    return;
  }
  for (const g of ls.games) {
    const row = document.createElement('div');
    row.className = 'card-d p-3 d-flex justify-content-between align-items-center';
    row.innerHTML = `
      <div>
        <div class="fw-semibold">${g.name}</div>
        <div class="small text-secondary">${g.mode} · Apuesta mín. $${Number(g.min_bet).toFixed(2)} · ${g.players_count}/${g.max_players} jugadores · ${g.in_play}</div>
      </div>
      <button class="btn btn-warning btn-sm join-btn">${g.players_count ? 'Unirse' : 'Crear mesa'}</button>`;
    row.querySelector('.join-btn').onclick = () => socket.emit('join_table', { game_id: g.id });
    box.appendChild(row);
  }
  const users = $('lobby-users');
  users.innerHTML = (ls.users && ls.users.length ? ls.users.map((u) => `<div>● ${u.name}</div>`).join('') : '<div class="text-secondary">Solo tú por ahora…</div>');
}

function renderTable(t) {
  state.table = t;
  // status
  const statusEl = $('t-status');
  if (t.status === 'waiting') {
    statusEl.textContent = `Esperando jugadores (${t.players.length}/${t.game_id ? '4' : '4'}) — apuesta $${Number(t.min_bet).toFixed(2)}`;
    statusEl.className = 'badge-status mt-1 badge bg-warning text-dark';
  } else if (t.status === 'playing') {
    statusEl.textContent = 'En partida';
    statusEl.className = 'badge-status mt-1 badge bg-success';
  } else {
    statusEl.textContent = 'Finalizada';
    statusEl.className = 'badge-status mt-1 badge bg-secondary';
  }
  $('t-match').textContent = t.match_id.slice(0, 8);

  // board
  const board = $('board');
  board.innerHTML = '';
  if (t.board.length === 0) board.innerHTML = '<span class="text-secondary small">La mesa está vacía — se reparten las fichas al iniciar.</span>';
  else t.board.forEach((tile) => board.appendChild(tileEl(tile, { played: true })));

  // seats
  const seats = $('seats');
  seats.innerHTML = '';
  for (const p of t.players) {
    const el = document.createElement('div');
    el.className = `card-d p-2 seat ${p.is_turn ? 'turn' : ''} ${p.user_id === state.myUserId ? 'me' : ''}`;
    el.innerHTML = `<div class="d-flex justify-content-between">
      <span>${p.user_id === state.myUserId ? '🟡 ' : ''}${p.name} ${p.connected ? '' : '<span class="text-danger">(desconectado)</span>'}</span>
      <span class="text-secondary small">${p.hand_size} fichas · <span class="text-warning fw-semibold">${p.score} pts</span></span>
    </div>`;
    seats.appendChild(el);
  }

  // hand (private)
  const hand = $('hand');
  hand.innerHTML = '';
  const mySeat = t.players.find((p) => p.user_id === state.myUserId);
  const isMyTurn = t.current_turn === state.myUserId;
  $('turn-hint').textContent = !t.players.some((p) => p.user_id === state.myUserId)
    ? ''
    : isMyTurn ? '▶ Es tu turno — juega una ficha o pasa' : '⏳ Esperando turno…';

  $('btn-start').classList.toggle('hidden', !(t.status === 'waiting' && t.players[0]?.user_id === state.myUserId && t.players.length >= 2));
  $('btn-pass').classList.toggle('hidden', !(t.status === 'playing' && isMyTurn));

  for (const tile of t.hand || []) {
    const playable = isMyTurn && (t.legal_moves || []).some((m) => m.tile[0] === tile[0] && m.tile[1] === tile[1]);
    const el = tileEl(tile, { private: true, playable });
    if (playable) {
      el.onclick = () => socket.emit('play_tile', { tile });
    }
    hand.appendChild(el);
  }
}

function addChat(name, msg) {
  const line = document.createElement('div');
  line.innerHTML = `<span class="text-info fw-semibold">${name}:</span> ${msg.replace(/</g, '&lt;')}`;
  $('chat-log').appendChild(line);
  $('chat-log').scrollTop = $('chat-log').scrollHeight;
}

// ---------------------------------------------------------------- socket
const socket = io({ query: { token } });

socket.on('user_ready', (u) => {
  state.user = u;
  state.myUserId = u.id;
  state.balance = u.balance;
  $('lbl-name').textContent = u.name;
  $('lbl-balance').textContent = `Saldo: $${Number(u.balance).toFixed(2)}`;
});

socket.on('auth_error', (e) => {
  toast(e.message, 'error');
  setTimeout(() => { location.href = '/'; }, 1200);
});

socket.on('lobby_state', (ls) => {
  renderLobby(ls);
  if (!state.matchId) showView('lobby');
});

socket.on('table_state', (t) => {
  state.matchId = t.match_id;
  showView('table');
  renderTable(t);
  if (state.user) $('lbl-balance').textContent = `Saldo: $${Number(state.balance).toFixed(2)}`;
});

socket.on('hand_winner', (h) => {
  toast(`🏆 ${h.winner_name} gana la mano (+${h.points} pts) ${h.label ? '· ' + h.label : ''}`, 'ok');
});

socket.on('match_finished', (m) => {
  toast(`🏆 ¡${m.winner_name} gana la partida! Premio: $${Number(m.prize).toFixed(2)} (pote $${Number(m.pot).toFixed(2)})`, 'ok');
  state.matchId = null;
  setTimeout(() => showView('lobby'), 2500);
});

socket.on('join_error', (e) => toast(e.message, 'error'));
socket.on('invalid_move', (e) => toast(e.reason, 'error'));
socket.on('error', (e) => toast(e.message, 'error'));
socket.on('chat', (c) => addChat(c.name, c.message));

// ------------------------------------------------------------------ actions
$('btn-start').onclick = () => socket.emit('start');
$('btn-pass').onclick = () => socket.emit('pass');
$('btn-resign').onclick = () => {
  if (state.matchId) socket.emit('resign');
  state.matchId = null;
  showView('lobby');
};
$('chat-send').onclick = sendChat;
$('chat-input').addEventListener('keydown', (e) => { if (e.key === 'Enter') sendChat(); });

function sendChat() {
  const msg = $('chat-input').value.trim();
  if (!msg) return;
  socket.emit('chat', { message: msg });
  $('chat-input').value = '';
}

// Ask the server for a fresh lobby state once connected
socket.on('connect', () => {
  socket.emit('refresh_lobby');
});