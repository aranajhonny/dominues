// backendClient.js — thin HTTP client for the Laravel backend.
// The game service NEVER writes money directly; the backend is authoritative.
const config = require('./config');

const TIMEOUT_MS = 8000;

async function request(method, path, { token, body } = {}) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
  try {
    const res = await fetch(`${config.apiUrl}${path}`, {
      method,
      headers: {
        Accept: 'application/json',
        ...(body ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const err = new Error(data.error || data.message || `HTTP ${res.status}`);
      err.status = res.status;
      err.data = data;
      throw err;
    }
    return data;
  } finally {
    clearTimeout(timer);
  }
}

module.exports = {
  validate: (token) => request('POST', '/public/api/game/validate', { body: { token } }),
  join: (token, gameId) => request('POST', '/public/api/game/join', { token, body: { game_id: gameId } }),
  refund: (token, gameId) => request('POST', '/public/api/game/refund', { token, body: { game_id: gameId } }),
  result: (token, matchId, winnerId) =>
    request('POST', '/public/api/game/result', { token, body: { match_id: matchId, winner_id: winnerId } }),
  listGames: () => request('GET', '/public/api/games'),
};