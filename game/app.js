// app.js — Dominues game service entrypoint.
const http = require('node:http');
const path = require('node:path');
const express = require('express');
const cors = require('cors');
const { Server } = require('socket.io');
const config = require('./src/config');
const registerSocketHandlers = require('./src/socketHandlers');
const registry = require('./src/registry');

const app = express();
app.use(cors({ origin: config.corsOrigin, credentials: true }));
app.use(express.static(path.join(__dirname, 'public')));

app.get('/health', (_req, res) => res.json({ status: 'ok' }));
app.get('/stats', (_req, res) => {
  res.json({ sockets: io.engine.clientsCount, activeTables: registry.tables.size });
});

const server = http.createServer(app);
const io = new Server(server, {
  cors: { origin: config.corsOrigin, methods: ['GET', 'POST'] },
});

registerSocketHandlers(io);

server.listen(config.port, () => {
  console.log(`[dominues-game] listening on :${config.port}  api=${config.apiUrl}`);
});