#!/usr/bin/env bash

set -euo pipefail

# Usage:
#   ./deploy-hosting.sh cutover
#   ./deploy-hosting.sh normal
#
# Env overrides:
#   RUN_DOMAIN_BACKFILL=1
#   PHP_BIN=/path/to/php

MODE="${1:-cutover}"
RUN_DOMAIN_BACKFILL="${RUN_DOMAIN_BACKFILL:-0}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

if [[ ! -f "./deploy.sh" ]]; then
  echo "ERROR: deploy.sh not found in $SCRIPT_DIR"
  exit 1
fi

if [[ ! -f "./artisan" ]]; then
  echo "ERROR: artisan not found in $SCRIPT_DIR"
  exit 1
fi

PHP_BIN="${PHP_BIN:-/opt/cpanel/ea-php82/root/usr/bin/php}"
if [[ ! -x "$PHP_BIN" ]]; then
  PHP_BIN="$(command -v php || true)"
fi
if [[ -z "${PHP_BIN:-}" || ! -x "$PHP_BIN" ]]; then
  echo "ERROR: Could not find a working PHP binary."
  exit 1
fi

case "$MODE" in
  cutover)
    CUTOVER_MODE=1
    ;;
  normal)
    CUTOVER_MODE=0
    ;;
  *)
    echo "ERROR: Invalid mode '$MODE'. Use: cutover | normal"
    exit 1
    ;;
esac

LOG_DIR="$SCRIPT_DIR/storage/logs"
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/deploy-$(date +%Y%m%d-%H%M%S).log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "==> Deployment wrapper started"
echo "==> Mode: $MODE"
echo "==> Log: $LOG_FILE"

on_error() {
  echo "==> Deployment failed."
  if [[ "$CUTOVER_MODE" == "1" ]]; then
    echo "==> Attempting to leave maintenance mode..."
    "$PHP_BIN" artisan up || true
  fi
  echo "==> Check log: $LOG_FILE"
}
trap on_error ERR

CUTOVER_MODE="$CUTOVER_MODE" RUN_DOMAIN_BACKFILL="$RUN_DOMAIN_BACKFILL" bash ./deploy.sh

echo "==> Wrapper finished successfully."
