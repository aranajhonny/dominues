# Dominues Backend — Laravel API + Admin Panel

Single source of truth: users, balances, KYC, transactions and match settlement.
See the repo root `README.md` for the full stack.

## Stack

- Laravel (latest stable line) — API + Livewire admin panel
- Sanctum (portal authentication), Laravel Reverb (self-hosted WebSockets)
- MySQL 8 (containerized via `docker-compose.yml`; SQLite works for local dev)
- Livewire 4 full-page components for the backoffice (`layouts::app` layout)

## API base

`/public/api` (matches the production base path). Key endpoints:

| Method | Path                  | Auth          | Purpose |
|--------|-----------------------|---------------|---------|
| POST   | `register` / `login`  | —             | Portal auth (Sanctum token) |
| GET    | `me`                  | sanctum       | Profile + balance + kyc_status |
| POST   | `deposits`            | sanctum       | Deposit request (+ proof) |
| POST   | `withdrawals`         | sanctum       | Withdrawal **reserves** the amount |
| GET    | `withdraw/requirements` | sanctum     | Playthrough progress |
| POST   | `kyc`                 | sanctum       | KYC document upload |
| GET    | `games`               | sanctum       | Active games/tables |
| POST   | `game/session`        | sanctum       | Issue short-lived game token |
| POST   | `game/validate`       | —             | Game service token check |
| POST   | `game/join|refund|result` | game token | Money hooks for the Node service |

## Money rules (critical controls from the delivery spec)

- Deposits are credited **exactly once** (idempotent by status).
- Withdrawals **reserve** at request time (available → reserved). Approve never
  re-debits; reject refunds only the reservation. All inside row-locked
  transactions (`app/Services/WalletService.php`).
- Playthrough: a configurable % of net approved deposits must be played before
  withdrawing (`settings.playthrough_percent`).
- KYC must be `approved` to withdraw.
- Match settlement: pot from real stakes → fee → single payout, idempotent by
  `matches.status`.

## Backoffice

`/admin` (roles: `admin` full · `host` Principal section only). Middleware
`admin` gates the panel; section guards (`render()` + action guards) enforce
the role matrix — hiding a link is never authorization.

## Local dev

```bash
cp .env.local.example .env
touch database/database.sqlite
composer install && php artisan key:generate
php artisan migrate --seed      # idempotent seeders
php artisan serve --port=8000
```

Seed accounts: `admin@dominues.local/admin123` · `host@dominues.local/host123`
· `jugador@dominues.local/jugador123` (balance $1,000).