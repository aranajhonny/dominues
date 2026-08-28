// test-e2e.js — 4 bot players play a full domino match against the real backend.
// Run: node test-e2e.js   (backend on :8000, game on :8081)
const { io } = require('socket.io-client');

const API = 'http://127.0.0.1:8000/public/api';
const GAME = 'http://127.0.0.1:8083';

async function api(path, { token, body, method = 'POST' } = {}) {
  const res = await fetch(`${API}${path}`, {
    method,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    body: body ? JSON.stringify(body) : undefined,
  });
  return res.json();
}

async function makePlayer(i) {
  const email = `bot${i}@dominues.local`;
  await api('/register', { body: { name: `Bot ${i}`, email, password: 'botbot123', password_confirmation: 'botbot123' } }).catch(() => {});
  const login = await api('/login', { body: { email, password: 'botbot123' } });
  const sess = await api('/game/session', { token: login.token, body: { game_id: 1 } });
  return { email, sanctum: login.token, gameToken: sess.token };
}

const delay = (ms) => new Promise((r) => setTimeout(r, ms));

async function main() {
  const players = [];
  for (let i = 1; i <= 4; i++) players.push(await makePlayer(i));
  console.log('4 players + game tokens OK');

  const logs = { states: 0, handWins: 0, chats: 0, errors: [] };
  let finished = null;
  const resolveFinish = (x) => { if (!finished) finished = x; };

  const sockets = players.map((p, idx) => {
    const s = io(GAME, { query: { token: p.gameToken }, transports: ['websocket'] });
    s.on('connect', () => console.log(`[bot${idx + 1}] connected`));
    s.on('user_ready', (u) => { s._uid = u.id; });
    s.on('table_state', (t) => {
      logs.states++;
      if (t.status === 'playing' && t.current_turn === s._uid) {
        const move = (t.legal_moves || [])[0];
        setTimeout(() => {
          if (move) s.emit('play_tile', { tile: move.tile });
          else s.emit('pass');
        }, 30);
      }
    });
    s.on('hand_winner', () => logs.handWins++);
    s.on('match_finished', (m) => {
      console.log(`\n🏆 MATCH FINISHED: winner=${m.winner_name} prize=${m.prize} pot=${m.pot} fee=${m.fee}`);
      resolveFinish({ match: m, socket: s, player: p });
    });
    s.on('join_error', (e) => logs.errors.push(`join: ${e.message}`));
    s.on('invalid_move', (e) => logs.errors.push(`move: ${e.reason}`));
    s.on('error', (e) => logs.errors.push(`err: ${e.message}`));
    return s;
  });

  function pUserIdx(_p) { return null; } // replaced by socket._uid from user_ready

  // Wait for connect + join
  await delay(800);
  for (const s of sockets) s.emit('join_table', { game_id: 1 });
  await delay(900);

  // Retry start until a bot reports the table is playing.
  let started = false;
  const startTimer = setInterval(() => {
    if (started) return clearInterval(startTimer);
    for (const s of sockets) s.emit('start');
  }, 400);
  const startedProbe = setInterval(() => {
    if (startedFlag) { started = true; clearInterval(startedProbe); }
  }, 200);
  let startedFlag = false;
  const _origOn = sockets[0].on;
  sockets[0].on('table_state', (t) => { if (t.status === 'playing') startedFlag = true; });

  // Watch for match finish with overall timeout
  const deadline = Date.now() + 90_000;
  while (!finished && Date.now() < deadline) {
    await delay(300);
  }

  if (!finished) {
    console.log('TIMEOUT without finish — resigning all bots');
    for (const s of sockets) s.emit('resign');
    await delay(600);
  }

  const winnerIdx = finished ? players.findIndex((p) => finished.match.winner_name === `Bot ${p.email.split('@')[0].replace('bot', '')}`) : null;
  // Verify backend money for a player in the finished match
  const verify = await api(`/me`, { token: players[0].sanctum, method: 'GET' });
  console.log('\n== BACKEND VERIFY (player 1) ==');
  console.log('balance:', verify.user.balance, 'reserved:', verify.user.reserved_balance, 'kyc:', verify.user.kyc_status);

  const tx = await api('/transactions', { token: players[1].sanctum, method: 'GET' });
  const known = ['deposit', 'withdrawal', 'game_stake', 'game_win', 'refund'];
  console.log('tx count player2:', tx.transactions.length, 'types:', [...new Set(tx.transactions.map((t) => t.type))]);

  const txP1 = await api('/transactions', { token: players[0].sanctum, method: 'GET' });
  const stakes = txP1.transactions.filter((t) => t.type === 'game_stake');
  console.log('stakes player1:', stakes.length, 'statuses:', [...new Set(stakes.map((s) => s.status))]);

  console.log('\n== SUMMARY ==');
  console.log('states:', logs.states, 'hand_wins:', logs.handWins, 'errors:', logs.errors.length ? logs.errors : 'none');

  sockets.forEach((s) => s.close());
  process.exit(finished ? 0 : 2);
}

main().catch((e) => { console.error('E2E FAIL:', e.message); process.exit(1); });