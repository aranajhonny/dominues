# Dominues — Homelab / Docker Edition

Full private-homelab reimplementation of the **Dominues** domino betting platform
(authoritative production spec: *Documento de entrega, 14/08/2026*), as a single
Dockerized monorepo. Every service from the production architecture is included;
nothing depends on external hosting (Vercel / Render / InterServer / Pusher).

```
┌──────────┐   HTTPS   ┌──────────────┐    HTTP    ┌───────────────────────┐
│  Portal  │ ─────────► │   Backend    │ ◄────────► │        MySQL 8        │
│  Vue 3   │   :8080    │ Laravel 11*  │  :3306    │   (source of truth)   │
└──────────┘           └──────┬───────┘            └───────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │ WSS (Pusher proto)  │ HTTP (game API)     │
   ┌────▼─────┐          ┌────▼─────┐
   │  Reverb  │          │   Game   │  Node 20 + Express + Socket.IO (engine)
   │  :8082   │          │  :8081   │  — authoritative double-six domino logic
   └──────────┘          └──────────┘  — money ops delegated to the backend
```

> \* The production build was Laravel 11. Composer now blocks 11.x installs on
> security advisories, so this build runs the latest stable Laravel (12/13 line)
> with an identical API surface. Migrations, controllers and views are written
> to match the production behavior documented in the delivery spec.

## Services

| Service    | Container        | Port (host) | Notes |
|------------|------------------|-------------|-------|
| Portal     | `dominues-portal`  | **8080**  | Vue 3 SPA (nginx) — login, deposits, KYC, withdrawals, game launcher |
| Backend    | `dominues-backend` | **8000**  | Laravel API + admin panel (`http://localhost:8000/admin`) |
| API base   | —                | —           | `http://localhost:8000/public/api` |
| Game       | `dominues-game`   | **8081**  | Node.js domino engine (Socket.IO) |
| Reverb     | `dominues-reverb` | **8082**  | Self-hosted WebSocket server (Pusher protocol) |
| MySQL      | `dominues-mysql`  | **3307**  | Credentials from `.env` |

## Quick start

```bash
cp .env.example .env          # defaults work out of the box
docker compose up -d --build
```

The backend container waits for MySQL, runs migrations and idempotent seeders
on every boot. Give it ~60s on first start.

| URL | Purpose |
|-----|---------|
| `http://localhost:8080` | Player portal |
| `http://localhost:8000/admin` | Backoffice (login below) |

### Demo accounts (seeded automatically)

| Role | Email | Password | Access |
|------|-------|----------|--------|
| Admin | `admin@dominues.local` | `admin123` | Full panel (dashboard, transactions, KYC, users, config) |
| Host  | `host@dominues.local`  | `host123`  | Principal section only (dashboard, transactions, KYC, profile) |
| Client| `jugador@dominues.local` | `jugador123` | Portal with $1,000 demo balance |

## Business flows (as delivered)

1. **Signup / session** — Sanctum tokens; roles gate API and panel routes.
2. **Deposit** — client requests + proof; panel approves once → balance credited
   exactly once (idempotent by status).
3. **Play** — portal opens the game with a short-lived game token; joining a
   table debits the stake; when the match ends the backend settles the pot
   (10% platform fee from config) and pays the winner once. The Node service
   **never touches money** — the backend is the single source of truth.
4. **KYC** — upload document → pending → admin approves/rejects (rejection
   requires a reason). Only `approved` unlocks withdrawals.
5. **Withdrawal** — amount is **reserved** at request time (debit available →
   move to reserved). Approve does **not** re-debit; reject refunds back.
   Playthrough requirement: a configurable % of net approved deposits must
   have been played (`settings.playthrough_percent`, default 100%).

## Operations

```bash
# Full stack
docker compose up -d --build
docker compose logs -f backend game

# Rebuild one service
docker compose up -d --build game

# Database (host port 3307 avoids clashing with local MySQL)
mysql -h 127.0.0.1 -P 3307 -u dominues -p dominues

# Backend inside the container
docker compose exec backend php artisan about
docker compose exec backend php artisan migrate:status
docker compose exec backend php artisan tinker
```

Migrations are run automatically by the entrypoint; on schema changes just
restart the backend container.

## Notes & known decisions (mirroring the delivery spec)

- **BlockBee** is disabled in the public experience (`settings.blockbee_enabled=0`).
  Re-enable only after an end-to-end test and reconciliation.
- **Bonuses** show **"No configurado"** in the panel — never fake zero income
  without a real bonus module.
- **Reverb** is self-hosted; Pusher-named packages remain only as
  protocol-compatible clients of Reverb.
- **KYC images** are stored base64 in the DB (homelab). For anything beyond a
  sandbox, move them to encrypted object storage and define a retention policy.
- The money engine is **not** a real payment processor: deposits/withdrawals
  are operator-confirmed movements. Do not use for real funds.
- **Port conflicts on this machine**: the game service binds **8081**; if
  plataforma-track's local dev server is running (also 8081), start the game
  with a different port: `PORT=8083 API_URL=http://localhost:8000 node app.js`.

## Local dev without Docker

```bash
# Backend (PHP 8.3+, Composer)
cd backend
cp .env.local.example .env
touch database/database.sqlite
composer install && php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000

# Portal (Node 20+)
cd portal
npm install
VITE_API_URL=http://localhost:8000/public npm run dev   # then open :5173
```

## Repository layout

```
backend/   Laravel API + Livewire admin panel (Dockerfile, entrypoint)
portal/    Vue 3 SPA (Vite, Pinia, Bootstrap 5) → nginx
game/      Node.js domino engine (Express + Socket.IO) — see its README
db/        reserved for init scripts
docker-compose.yml   the whole stack
.env.example         env template (no secrets)
```