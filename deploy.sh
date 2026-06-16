#!/usr/bin/env bash

set -euo pipefail

CUTOVER_MODE="${CUTOVER_MODE:-0}"
RUN_DOMAIN_BACKFILL="${RUN_DOMAIN_BACKFILL:-0}"

# Change to script directory (repo root)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Prefer cPanel PHP 8.2 binary, but allow override via PHP_BIN env var.
PHP_BIN="${PHP_BIN:-/opt/cpanel/ea-php82/root/usr/bin/php}"

# Fallback to system php when custom binary does not exist.
if [ ! -x "$PHP_BIN" ]; then
  PHP_BIN="$(command -v php || true)"
fi

if [ -z "${PHP_BIN:-}" ] || [ ! -x "$PHP_BIN" ]; then
  echo "ERROR: Could not find a working PHP binary."
  exit 1
fi

# Resolve composer command.
if command -v composer >/dev/null 2>&1; then
  COMPOSER_CMD=("$PHP_BIN" "$(command -v composer)")
elif [ -f "composer.phar" ]; then
  COMPOSER_CMD=("$PHP_BIN" "composer.phar")
else
  echo "ERROR: Composer not found. Install composer or place composer.phar in repo root."
  exit 1
fi

echo "==> Using PHP: $("$PHP_BIN" -v | sed -n '1p')"

if [ "$CUTOVER_MODE" = "1" ]; then
  echo "==> Entering maintenance mode for direct cutover..."
  "$PHP_BIN" artisan down || true
fi

echo "==> Pulling latest code..."
git pull --ff-only

echo "==> Installing production dependencies..."
"${COMPOSER_CMD[@]}" install --no-dev --optimize-autoloader --no-interaction

echo "==> Running database migrations..."
"$PHP_BIN" artisan migrate --force

echo "==> Ensuring documents union view exists..."
"$PHP_BIN" artisan documents:ensure-union-view --force

if [ "$RUN_DOMAIN_BACKFILL" = "1" ]; then
  echo "==> Running documents domain backfill..."
  "$PHP_BIN" artisan documents:backfill-domain-tables --chunk=200
  echo "==> Running parity verification..."
  "$PHP_BIN" artisan documents:verify-domain-parity --sample=25
fi

echo "==> Running database seeders..."
"$PHP_BIN" artisan db:seed --class=RolesAndPermissionsSeeder --force --no-interaction


echo "==> Clearing framework caches (keeping application cache for AI summaries)..."
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan view:clear
"$PHP_BIN" artisan event:clear || true

echo "==> Rebuilding caches..."
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache || true
"$PHP_BIN" artisan optimize

echo "==> Validating anonymous feedback token config..."
AF_TOKEN="$("$PHP_BIN" artisan tinker --execute="echo (string) config('anonymous_feedback.public_token');" | tr -d '\r')"
if [ -z "$AF_TOKEN" ]; then
  echo "ERROR: ANONYMOUS_FEEDBACK_PUBLIC_TOKEN is empty in runtime config."
  exit 1
fi
if printf '%s' "$AF_TOKEN" | /usr/bin/env grep -q '[[:space:]]'; then
  echo "ERROR: ANONYMOUS_FEEDBACK_PUBLIC_TOKEN contains whitespace. Remove spaces/newlines from .env."
  exit 1
fi
echo "==> Anonymous feedback URL:"
echo "    $(/usr/bin/env sed -n 's|^APP_URL=||p' .env | head -n 1)/feedback/$AF_TOKEN"

echo "==> Validating Gemini AI config..."
GEMINI_KEY_SET="$("$PHP_BIN" artisan tinker --execute="echo filled(config('services.gemini.api_key')) ? 'yes' : '';" | tr -d '\r')"
GEMINI_MODEL="$("$PHP_BIN" artisan tinker --execute="echo (string) config('services.gemini.model');" | tr -d '\r')"
GEMINI_CACHE_ENABLED="$("$PHP_BIN" artisan tinker --execute="echo config('services.gemini.summary_cache_enabled') ? 'yes' : 'no';" | tr -d '\r')"
CACHE_STORE="$("$PHP_BIN" artisan tinker --execute="echo (string) config('cache.default');" | tr -d '\r')"
GEMINI_RPM="$("$PHP_BIN" artisan tinker --execute="echo (string) config('services.gemini.max_requests_per_minute');" | tr -d '\r')"

if [ "$GEMINI_KEY_SET" != "yes" ]; then
  echo "WARNING: GEMINI_API_KEY is empty in runtime config. AI Summary buttons will be hidden."
else
  echo "    Gemini API key: configured"
  echo "    Gemini model: ${GEMINI_MODEL:-gemini-2.5-flash-lite}"
  echo "    Summary cache: ${GEMINI_CACHE_ENABLED:-yes}"
  echo "    Cache store: ${CACHE_STORE:-database}"
  echo "    Gemini max requests/min: ${GEMINI_RPM:-5}"
  if [ "${CACHE_STORE:-database}" != "database" ]; then
    echo "WARNING: CACHE_STORE is '${CACHE_STORE}'. Use CACHE_STORE=database so AI summaries persist."
  fi
fi

if [ "$CUTOVER_MODE" = "1" ]; then
  echo "==> Leaving maintenance mode..."
  "$PHP_BIN" artisan up
fi

echo "==> Deployment completed successfully."
