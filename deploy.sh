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

if grep -qE '^APP_ENV=production' .env 2>/dev/null; then
  echo "==> Production environment detected."
  echo "    Deploy uses forward-only migrations (migrate --force)."
  echo "    Destructive commands such as migrate:fresh and db:wipe are never run here."
fi

echo "==> Running database migrations (forward-only)..."
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

echo "==> Validating Groq AI config..."
GROQ_KEY_SET="$("$PHP_BIN" artisan tinker --execute="echo filled(config('services.groq.api_key')) ? 'yes' : '';" | tr -d '\r')"
GROQ_MODEL="$("$PHP_BIN" artisan tinker --execute="echo (string) config('services.groq.model');" | tr -d '\r')"
GROQ_CACHE_ENABLED="$("$PHP_BIN" artisan tinker --execute="echo config('services.groq.summary_cache_enabled') ? 'yes' : 'no';" | tr -d '\r')"
CACHE_STORE="$("$PHP_BIN" artisan tinker --execute="echo (string) config('cache.default');" | tr -d '\r')"
GROQ_RPM="$("$PHP_BIN" artisan tinker --execute="echo (string) config('services.groq.max_requests_per_minute');" | tr -d '\r')"

if [ "$GROQ_KEY_SET" != "yes" ]; then
  echo "WARNING: GROQ_API_KEY is empty in runtime config. AI Summary buttons will be hidden."
else
  echo "    Groq API key: configured"
  echo "    Groq model: ${GROQ_MODEL:-llama-3.3-70b-versatile}"
  echo "    Summary cache: ${GROQ_CACHE_ENABLED:-yes}"
  echo "    Cache store: ${CACHE_STORE:-database}"
  echo "    Groq max requests/min: ${GROQ_RPM:-30}"
  if [ "${CACHE_STORE:-database}" != "database" ]; then
    echo "WARNING: CACHE_STORE is '${CACHE_STORE}'. Use CACHE_STORE=database so AI summaries persist."
  fi
fi

echo "==> Ensuring Laravel scheduler cron is installed..."
# The scheduler drives automatic work period creation (Thursday 15:30), deadline
# reminders, and other recurring tasks. It only runs if `schedule:run` fires every
# minute. Install the cron entry idempotently for the current user.
#
# If `crontab` is unavailable/restricted on this host (some cPanel setups), add the
# following line manually via cPanel > Cron Jobs instead:
#   * * * * * /opt/cpanel/ea-php82/root/usr/bin/php <repo-path>/artisan schedule:run >> /dev/null 2>&1
SCHEDULE_CMD="$SCRIPT_DIR/artisan schedule:run"
CRON_LINE="* * * * * $PHP_BIN $SCHEDULE_CMD >> /dev/null 2>&1"
if command -v crontab >/dev/null 2>&1; then
  if crontab -l 2>/dev/null | grep -Fq "$SCHEDULE_CMD"; then
    echo "    Laravel scheduler cron already present."
  else
    ( crontab -l 2>/dev/null; echo "$CRON_LINE" ) | crontab -
    echo "    Installed Laravel scheduler cron: $CRON_LINE"
  fi
else
  echo "WARNING: 'crontab' not found. Add this cron manually via cPanel > Cron Jobs:"
  echo "         $CRON_LINE"
fi

if [ "$CUTOVER_MODE" = "1" ]; then
  echo "==> Leaving maintenance mode..."
  "$PHP_BIN" artisan up
fi

echo "==> Deployment completed successfully."
