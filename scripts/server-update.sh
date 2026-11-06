#!/usr/bin/env bash
# Run on the cPanel server (Terminal) from the app folder:  bash scripts/server-update.sh
# Pulls the latest tested release from the `deploy` branch and applies it. Safe to run repeatedly.
set -euo pipefail
cd "$(dirname "$0")/.."

PHP="${PHP:-php}"
echo "==> Current release: $(cat RELEASE 2>/dev/null || echo none)"

"$PHP" artisan down --retry=30 || true
trap '"$PHP" artisan up || true' EXIT

echo "==> Backing up the database before updating"
"$PHP" artisan academy:backup --keep=14 || echo "!! Backup failed - check MYSQLDUMP_PATH in .env (continuing)"

echo "==> Fetching release"
git fetch --depth=1 origin deploy
git reset --hard origin/deploy

echo "==> Migrating and caching"
"$PHP" artisan migrate --force
"$PHP" artisan storage:link 2>/dev/null || true
"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
"$PHP" artisan event:cache
"$PHP" artisan filament:optimize 2>/dev/null || true
"$PHP" artisan queue:restart

echo "==> Now running: $(cat RELEASE)"
