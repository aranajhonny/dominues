// registry.js — shared in-memory state across the game service modules.
module.exports = {
  tables: new Map(), // matchId -> Table
  byGame: new Map(), // gameId -> matchId (one active table per game)
  userIdToMatch: new Map(), // userId -> matchId
};