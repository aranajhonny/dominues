#!/bin/sh
# Dominues backend entrypoint — idempotent for homelab restarts.
set -e

cd /var/www/html

# 1. APP_KEY: generate once if not provided via env
php artisan key:generate --force --no-interaction || true

# 2. Wait for MySQL, then run migrations (no-op if already applied) and idempotent seeders
until php artisan migrate --force --no-interaction; do
  echo "[entrypoint] waiting for MySQL..."
  sleep 3
done

php artisan db:seed --force --no-interaction || echo "[entrypoint] seed skipped (already seeded)"

# 3. Storage link (idempotent)
php artisan storage:link --no-interaction || true

# 4. Clear caches so env changes take effect on restart
php artisan optimize:clear --no-interaction || true

# 5. Serve
exec php artisan serve --host=0.0.0.0 --port=80