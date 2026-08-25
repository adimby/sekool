#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
export DB_URL="${DB_URL:-${DATABASE_URL:-}}"
export DB_SSLMODE="${DB_SSLMODE:-require}"
export CACHE_STORE="${CACHE_STORE:-file}"
export SESSION_DRIVER="${SESSION_DRIVER:-file}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"
export HASH_DRIVER="${HASH_DRIVER:-bcrypt}"
export APP_ENV="${APP_ENV:-production}"

export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:8000}}"

if [ ! -f .env ]; then
  cp .env.example .env
fi

# Render generateValue is raw base64; Laravel needs the base64: prefix.
if [ -n "${APP_KEY:-}" ] && [[ "${APP_KEY}" != base64:* ]]; then
  export APP_KEY="base64:${APP_KEY}"
fi

if [ -z "${APP_KEY:-}" ]; then
  php artisan key:generate --force
fi

php artisan package:discover --ansi --no-interaction >/dev/null 2>&1 || true

php artisan demo:bootstrap

port="${PORT:-8000}"
echo "FANABE listening on 0.0.0.0:${port}"
exec php artisan serve --host=0.0.0.0 --port="${port}"
