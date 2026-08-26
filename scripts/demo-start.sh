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

read_dotenv_app_key() {
  sed -n 's/^APP_KEY=//p' .env | tail -1 | tr -d '\r' | tr -d '"' | tr -d "'"
}

persist_app_key() {
  if [ -d "${KEY_DIR}" ]; then
    printf '%s\n' "${APP_KEY}" > "${KEY_FILE}"
  fi
  # Keep .env in sync so artisan does not see an empty APP_KEY= from .env.example.
  if grep -q '^APP_KEY=' .env; then
    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
  else
    printf '\nAPP_KEY=%s\n' "${APP_KEY}" >> .env
  fi
}

# Dokploy/Compose inject APP_KEY="" which shadows a generated .env key.
case "${APP_KEY:-}" in
  ''|'REMPLACER'|'base64:REMPLACER'|'base64:'|'null'|'change-me')
    unset APP_KEY || true
    ;;
esac

if [ -z "${APP_KEY:-}" ] && [ -f "${KEY_FILE}" ]; then
  export APP_KEY="$(tr -d '[:space:]' < "${KEY_FILE}")"
fi

if [ -n "${APP_KEY:-}" ] && [[ "${APP_KEY}" != base64:* ]]; then
  export APP_KEY="base64:${APP_KEY}"
fi

if [ -z "${APP_KEY:-}" ]; then
  php artisan key:generate --force --no-interaction
  export APP_KEY="$(read_dotenv_app_key)"
fi

if [ -z "${APP_KEY:-}" ]; then
  echo "FANABE: APP_KEY is still empty after key:generate" >&2
  exit 1
fi

persist_app_key

php artisan package:discover --ansi --no-interaction >/dev/null 2>&1 || true

php artisan demo:bootstrap

port="${PORT:-8000}"
echo "FANABE listening on 0.0.0.0:${port}"
exec php artisan serve --host=0.0.0.0 --port="${port}"
