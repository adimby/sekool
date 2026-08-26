#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
export DB_URL="${DB_URL:-${DATABASE_URL:-}}"
export DATABASE_URL="${DATABASE_URL:-${DB_URL:-}}"
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

upsert_env() {
  php -r '
    $k = $argv[1];
    $v = $argv[2];
    $path = ".env";
    $env = is_file($path) ? file_get_contents($path) : "";
    $line = $k."=".$v;
    $pattern = "/^".preg_quote($k, "/")."=.*/m";
    if (preg_match($pattern, $env) === 1) {
        $env = preg_replace($pattern, $line, $env, 1);
    } else {
        $env = rtrim($env)."\n".$line."\n";
    }
    file_put_contents($path, $env);
  ' -- "$1" "$2"
}

# php artisan serve does not pass DATABASE_URL to its child process.
# Inside Docker, if no URL is set, talk to the Compose service `db`.
if [ -z "${DB_URL}" ] && [ -f /.dockerenv ]; then
  export DB_HOST="${DB_HOST:-db}"
  if [ "${DB_HOST}" = "127.0.0.1" ] || [ "${DB_HOST}" = "localhost" ]; then
    export DB_HOST=db
  fi
  export DB_PORT="${DB_PORT:-5432}"
  export DB_DATABASE="${DB_DATABASE:-fanabe}"
  export DB_USERNAME="${DB_USERNAME:-fanabe}"
  export DB_PASSWORD="${DB_PASSWORD:-fanabe}"
  export DB_SSLMODE="${DB_SSLMODE:-disable}"
  export DB_URL="postgres://${DB_USERNAME}:${DB_PASSWORD}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}"
  export DATABASE_URL="${DB_URL}"
fi

if [ -n "${DB_URL}" ]; then
  export DATABASE_URL="${DATABASE_URL:-${DB_URL}}"
  upsert_env DB_URL "${DB_URL}"
  upsert_env DATABASE_URL "${DATABASE_URL}"
  upsert_env DB_CONNECTION pgsql
  if [ -n "${DB_HOST:-}" ]; then
    upsert_env DB_HOST "${DB_HOST}"
  fi
  if [ -n "${DB_PORT:-}" ]; then
    upsert_env DB_PORT "${DB_PORT}"
  fi
  if [ -n "${DB_DATABASE:-}" ]; then
    upsert_env DB_DATABASE "${DB_DATABASE}"
  fi
  if [ -n "${DB_USERNAME:-}" ]; then
    upsert_env DB_USERNAME "${DB_USERNAME}"
  fi
  if [ -n "${DB_PASSWORD:-}" ]; then
    upsert_env DB_PASSWORD "${DB_PASSWORD}"
  fi
  upsert_env DB_SSLMODE "${DB_SSLMODE}"
fi

upsert_env APP_URL "${APP_URL}"
upsert_env APP_ENV "${APP_ENV}"

KEY_DIR="/var/fanabe-keys"
KEY_FILE="${KEY_DIR}/app.key"

read_dotenv_app_key() {
  sed -n 's/^APP_KEY=//p' .env | tail -1 | tr -d '\r' | tr -d '"' | tr -d "'"
}

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

if [ -d "${KEY_DIR}" ]; then
  printf '%s\n' "${APP_KEY}" > "${KEY_FILE}"
fi
upsert_env APP_KEY "${APP_KEY}"

php artisan package:discover --ansi --no-interaction >/dev/null 2>&1 || true

php artisan demo:bootstrap

port="${PORT:-8000}"
echo "FANABE listening on 0.0.0.0:${port}"
# Do not use `php artisan serve`: it strips DATABASE_URL/DB_* from the worker
# (only a small passthrough list is forwarded), so HTTP requests hit 127.0.0.1.
exec php -S "0.0.0.0:${port}" -t public \
  vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
