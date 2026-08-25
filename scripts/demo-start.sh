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

KEY_DIR="/var/fanabe-keys"
KEY_FILE="${KEY_DIR}/app.key"

normalize_app_key() {
  if [ -n "${APP_KEY:-}" ] && [[ "${APP_KEY}" != base64:* ]]; then
    export APP_KEY="base64:${APP_KEY}"
  fi
}

if [ -z "${APP_KEY:-}" ] && [ -f "${KEY_FILE}" ]; then
  export APP_KEY="$(tr -d '[:space:]' < "${KEY_FILE}")"
fi

normalize_app_key

if [ -z "${APP_KEY:-}" ]; then
  php artisan key:generate --force
  if [ -d "${KEY_DIR}" ]; then
    sed -n 's/^APP_KEY=//p' .env | head -1 | tr -d '[:space:]' > "${KEY_FILE}"
  fi
elif [ -d "${KEY_DIR}" ] && [ ! -f "${KEY_FILE}" ]; then
  printf '%s\n' "${APP_KEY}" > "${KEY_FILE}"
fi

php artisan package:discover --ansi --no-interaction >/dev/null 2>&1 || true

php artisan demo:bootstrap

port="${PORT:-8000}"
echo "FANABE listening on 0.0.0.0:${port}"
exec php artisan serve --host=0.0.0.0 --port="${port}"
