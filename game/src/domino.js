/**
 * domino.js — pure double-six domino engine for Dominues.
 * No I/O, no sockets, fully unit-testable.
 *
 * Board model: an array of played tiles in order. The open ends are:
 *   left end  = first tile's value NOT facing the previous tile (board[0][0])
 *   right end = last tile's value not facing the previous (board.at(-1)[1])
 * Tiles are always normalized [a, b] with a <= b.
 */

const TARGET = 100; // match target points (classic)

function allTiles() {
  const tiles = [];
  for (let a = 0; a <= 6; a++) {
    for (let b = a; b <= 6; b++) tiles.push([a, b]);
  }
  return tiles; // 28
}

function shuffle(arr, rng = Math.random) {
  const out = [...arr];
  for (let i = out.length - 1; i > 0; i--) {
    const j = Math.floor(rng() * (i + 1));
    [out[i], out[j]] = [out[j], out[i]];
  }
  return out;
}

function deal(numPlayers, rng = Math.random) {
  const tiles = shuffle(allTiles(), rng);
  const hands = Array.from({ length: numPlayers }, () => []);
  for (let i = 0; i < tiles.length; i++) {
    hands[i % numPlayers].push(tiles[i]);
  }
  return hands; // 7 each for 4 players (28 % 4 === 0)
}

/** Sum of pips in a hand. */
function handPoints(hand) {
  return hand.reduce((s, [a, b]) => s + a + b, 0);
}

/** Open ends of the current board. Returns [left, right] values (both can be equal). */
function openEnds(board) {
  if (board.length === 0) return [null, null];
  return [board[0][0], board[board.length - 1][1]];
}

/**
 * Is `tile` playable on this board?
 * A tile [a,b] connects if a or b matches either open end.
 * Doubles connect exactly like singles (only to a matching end); being a
 * double does not open a second branch in this ruleset.
 */
function canPlay(board, tile) {
  if (!Array.isArray(tile) || tile.length !== 2) return false;
  const [a, b] = tile;
  if (a < 0 || b < 0 || a > 6 || b > 6) return false;
  if (board.length === 0) return true; // any tile leads the first play
  const [left, right] = openEnds(board);
  return a === left || b === left || a === right || b === right;
}

/**
 * Place `tile` on the given side ('left'|'right'). Mutates nothing; returns
 * the new board or null when illegal.
 *
 * Invariant of the returned board: board[0][0] is the open LEFT end and
 * board.at(-1)[1] is the open RIGHT end (tiles are stored with orientation,
 * not normalized).
 */
function placeTile(board, tile, side) {
  const [a, b] = tile;

  if (board.length === 0) return [tile.slice()];

  if (side === 'left') {
    const left = board[0][0];
    const outward = a === left ? b : b === left ? a : null;
    if (outward === null) return null;
    return [[outward, left]].concat(board);
  }
  if (side === 'right') {
    const right = board[board.length - 1][1];
    const outward = a === right ? b : b === right ? a : null;
    if (outward === null) return null;
    return board.concat([[right, outward]]);
  }
  return null;
}

function normalize([a, b]) {
  return a <= b ? [a, b] : [b, a];
}

/** All legal placement targets for a hand: [{tile, side}] or []. */
function legalMoves(board, hand) {
  const moves = [];
  for (const tile of hand) {
    if (!canPlay(board, tile)) continue;
    if (board.length === 0) {
      moves.push({ tile, side: 'right' });
      continue;
    }
    const [left, right] = openEnds(board);
    if (tile[0] === left || tile[1] === left) moves.push({ tile, side: 'left' });
    if (tile[0] === right || tile[1] === right) moves.push({ tile, side: 'right' });
  }
  return moves;
}

function removeTile(hand, tile) {
  const idx = hand.findIndex(([a, b]) => a === tile[0] && b === tile[1]);
  if (idx === -1) return null;
  const next = hand.slice();
  next.splice(idx, 1);
  return next;
}

/** Who holds the highest double (6-6 first). Returns player index or -1. */
function pickStarter(hands) {
  for (let d = 6; d >= 0; d--) {
    for (let p = 0; p < hands.length; p++) {
      if (hands[p].some(([a, b]) => a === d && b === d)) return p;
    }
  }
  return 0;
}

module.exports = {
  TARGET,
  allTiles,
  shuffle,
  deal,
  handPoints,
  openEnds,
  canPlay,
  placeTile,
  legalMoves,
  removeTile,
  pickStarter,
};

// ---------------------------------------------------------------------------
// Self-test (run directly: `node src/domino.js`)
// ---------------------------------------------------------------------------
if (require.main === module) {
  const assert = require('node:assert');

  const tiles = allTiles();
  assert.strictEqual(tiles.length, 28, '28 tiles');

  const hands = deal(4, () => 0.5);
  assert.ok(hands.every((h) => h.length === 7), '7 per player for 4 players');

  const starter = pickStarter(hands);
  assert.ok(starter >= 0 && starter < 4, 'starter index valid');

  // legal move on empty board
  assert.strictEqual(canPlay([], [6, 6]), true, 'any tile leads');
  assert.strictEqual(legalMoves([], [[6, 6]]).length, 1, 'one move on empty');

  // play 6-6, then 6-3 on the right, then 3-3 on the left end of 6-6? -> invalid (6 != 3)
  let board = placeTile([], [6, 6], 'right');
  board = placeTile(board, [6, 3], 'right');
  assert.deepStrictEqual(board, [[6, 6], [6, 3]], 'right extension oriented [6,6] [6,3]');
  assert.deepStrictEqual(openEnds(board), [6, 3], 'ends 6 and 3');

  // 6-1 connects on the left end (6)
  const b2 = placeTile(board, [6, 1], 'left');
  assert.ok(b2, 'left play legal');
  assert.deepStrictEqual(openEnds(b2), [1, 3], 'ends now 1 and 3');

  // 3-0 on the right end (3)
  const b3 = placeTile(b2, [0, 3], 'right');
  assert.ok(b3 && openEnds(b3)[1] === 0, 'right end now 0');

  // illegal: 5-5 does not match any end (1, 0)
  assert.strictEqual(canPlay(b3, [5, 5]), false, '5-5 rejected');
  assert.strictEqual(placeTile(b3, [5, 5], 'right'), null, 'illegal placed rejected');

  // legalMoves finds 0-x and 1-x
  const hand = [[1, 4], [0, 2], [5, 5], [3, 6]];
  const moves = legalMoves(b3, hand);
  assert.strictEqual(moves.length, 2, 'two legal moves for hand');

  // points
  assert.strictEqual(handPoints([[6, 6], [6, 5]]), 23, '23 pips');

  // simulate a fast 4-player hand with a seeded rng (deterministic-ish)
  const seats = deal(4, () => 0.42);
  let simBoard = [];
  let turn = pickStarter(seats);
  let steps = 0;
  while (steps < 500) {
    steps++;
    const moves = legalMoves(simBoard, seats[turn]);
    if (moves.length > 0) {
      const mv = moves[moves.length - 1]; // always play the last legal (keeps sim bounded)
      seats[turn] = removeTile(seats[turn], mv.tile);
      if (seats[turn].length === 0) break; // domino!
      simBoard = placeTile(simBoard, mv.tile, mv.side);
    } else {
      // pass
    }
    turn = (turn + 1) % 4;
  }
  const finished = seats.some((h) => h.length === 0);
  console.log(`self-test OK — sim ${steps} steps, ${finished ? 'hand finished by domino' : 'board closed'}`);
}