#!/usr/bin/env bash
#
# Post-pull deploy script for Hostinger.
# Run via SSH after Hostinger Git auto-deploy pulls new code:
#   bash deploy.sh
#
# Auto-detects the environment from APP_ENV in .env:
#   - production  -> optimized install, cached config, no dev deps
#   - staging     -> dev deps kept, caches cleared (easier debugging)
#
set -euo pipefail

cd "$(dirname "$0")"

# Read APP_ENV from .env (default: production if missing)
APP_ENV="$(grep -E '^APP_ENV=' .env 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" | xargs || true)"
APP_ENV="${APP_ENV:-production}"

echo "==> Deploying environment: ${APP_ENV}"

# Put app in maintenance mode (ignore if already down or not bootable)
php artisan down --render="errors::503" || true

if [ "${APP_ENV}" = "production" ]; then
    composer install --no-dev --optimize-autoloader --no-interaction
else
    composer install --optimize-autoloader --no-interaction
fi

# Run database migrations (non-interactive)
php artisan migrate --force

# Sync release feed ("What's New")
php artisan releases:sync || true

# Build front-end assets if a build pipeline is present
if [ -f package.json ]; then
    npm ci --no-audit --no-fund || npm install --no-audit --no-fund
    npm run build
fi

# Cache framework config for speed
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "${APP_ENV}" = "production" ]; then
    php artisan event:cache || true
fi

# Clear and warm any app-level caches
php artisan optimize

# Bring app back up
php artisan up

echo "==> Deploy complete (${APP_ENV})"
