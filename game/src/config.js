// config.js — environment / runtime configuration.
module.exports = {
  port: Number(process.env.PORT || 8081),
  apiUrl: (process.env.API_URL || 'http://backend:80').replace(/\/+$/, ''),
  corsOrigin: process.env.CORS_ORIGIN || 'http://localhost:8080',
};