#!/usr/bin/env bash

set -euo pipefail

# One-shot production cutover for the documents domain migration.
# Usage (from repo root on the server):
#   chmod +x production-document-domain-cutover.sh
#   CONFIRM=1 ./production-document-domain-cutover.sh
#
# Optional environment variables:
#   PHP_BIN=/path/to/php
#   GIT_BRANCH=big-change
#   BACKFILL_CHUNK=200
#   PARITY_SAMPLE=25
#   SKIP_BACKUP=1
#   SKIP_GIT_PULL=1
#   BACKUP_DIR=./storage/backups

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

PHP_BIN="${PHP_BIN:-/opt/cpanel/ea-php82/root/usr/bin/php}"
if [ ! -x "$PHP_BIN" ]; then
  PHP_BIN="$(command -v php || true)"
fi
if [ -z "${PHP_BIN:-}" ] || [ ! -x "$PHP_BIN" ]; then
  echo "ERROR: Could not find a working PHP binary."
  exit 1
fi

if command -v composer >/dev/null 2>&1; then
  COMPOSER_CMD=("$PHP_BIN" "$(command -v composer)")
elif [ -f "composer.phar" ]; then
  COMPOSER_CMD=("$PHP_BIN" "composer.phar")
else
  echo "ERROR: Composer not found. Install composer or place composer.phar in repo root."
  exit 1
fi

GIT_BRANCH="${GIT_BRANCH:-big-change}"
BACKFILL_CHUNK="${BACKFILL_CHUNK:-200}"
PARITY_SAMPLE="${PARITY_SAMPLE:-25}"
BACKUP_DIR="${BACKUP_DIR:-./storage/backups}"
KNOWN_GOOD_COMMIT="${KNOWN_GOOD_COMMIT:-116015368d98416b7b9c56b2ac51e6e7d310fbd5}"

if [ "${CONFIRM:-}" != "1" ]; then
  cat <<EOF
This script will:
  1. Put the app in maintenance mode
  2. Create a MySQL backup (unless SKIP_BACKUP=1)
  3. Pull branch ${GIT_BRANCH}
  4. Install Composer dependencies
  5. Run migrations, domain backfill, and parity verification
  6. Rebuild caches and bring the app back up

Stop cron/scheduler jobs before running.

Re-run with:
  CONFIRM=1 ./production-document-domain-cutover.sh
EOF
  exit 1
fi

if [ ! -f ".env" ]; then
  echo "ERROR: .env not found in ${SCRIPT_DIR}"
  exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

for var in DB_HOST DB_PORT DB_USERNAME DB_PASSWORD DB_DATABASE; do
  if [ -z "${!var:-}" ]; then
    echo "ERROR: ${var} is not set in .env"
    exit 1
  fi
done

if ! command -v mysqldump >/dev/null 2>&1; then
  echo "ERROR: mysqldump not found. Install it or set SKIP_BACKUP=1 after taking a manual backup."
  exit 1
fi

echo "==> Using PHP: $("$PHP_BIN" -v | sed -n '1p')"
echo "==> Entering maintenance mode..."
"$PHP_BIN" artisan down || true

BACKUP_FILE=""
if [ "${SKIP_BACKUP:-0}" != "1" ]; then
  mkdir -p "$BACKUP_DIR"
  BACKUP_FILE="${BACKUP_DIR}/pre_cutover_$(date +%Y%m%d_%H%M%S).sql"
  echo "==> Creating database backup: ${BACKUP_FILE}"
  mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" \
    --single-transaction --routines --triggers --quick \
    "$DB_DATABASE" > "$BACKUP_FILE"
else
  echo "==> SKIP_BACKUP=1 set; ensure you already have a fresh database backup."
fi

if [ "${SKIP_GIT_PULL:-0}" != "1" ]; then
  echo "==> Pulling branch ${GIT_BRANCH}..."
  git fetch origin
  git checkout "$GIT_BRANCH"
  git pull --ff-only origin "$GIT_BRANCH"
else
  echo "==> SKIP_GIT_PULL=1 set; using current checkout."
fi

echo "==> Installing production dependencies..."
"${COMPOSER_CMD[@]}" install --no-dev --optimize-autoloader --no-interaction

echo "==> Running database migrations..."
"$PHP_BIN" artisan migrate --force

echo "==> Backfilling documents domain tables..."
"$PHP_BIN" artisan documents:backfill-domain-tables --chunk="$BACKFILL_CHUNK"

echo "==> Verifying domain parity..."
if ! "$PHP_BIN" artisan documents:verify-domain-parity --sample="$PARITY_SAMPLE"; then
  cat <<EOF

ERROR: Parity verification failed.
The app is still in maintenance mode.

Rollback options:
  1. git checkout ${KNOWN_GOOD_COMMIT}
  2. Restore the pre-cutover backup${BACKUP_FILE:+ at ${BACKUP_FILE}}
  3. php artisan optimize:clear && php artisan optimize
  4. php artisan up
EOF
  exit 1
fi

echo "==> Clearing and rebuilding caches..."
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache || true
"$PHP_BIN" artisan optimize

echo "==> Leaving maintenance mode..."
"$PHP_BIN" artisan up

echo "==> Cutover completed successfully."
if [ -n "$BACKUP_FILE" ]; then
  echo "    Backup: ${BACKUP_FILE}"
fi
echo "    Re-enable cron/scheduler jobs and smoke-test weekly plans, reports, budget review, and action logs."
