#!/usr/bin/env bash
#
# Deploy the API. Run from the repo root on the target host.
#
#   ./scripts/deploy.sh
#
# Order matters and each step is safe to re-run:
#
#   1. migrate   schema changes AND config value changes. A value that already
#                ships and needs to CHANGE is a migration, because migrations
#                run once per environment and are ordered. See
#                2026_08_19_000001_expand_player_positions_config.
#   2. db:seed   AdminConfigSeeder inserts config keys that do not exist yet
#                and touches nothing else, so a NEW key added in code reaches
#                every environment on the next deploy without a manual step.
#                It never overwrites a key an admin has changed.
#   3. caches    rebuilt last, after the config rows they will cache are final.
#
# Why both: the seeder can only add keys, never change one. The migration can
# change one but only runs once. Together they cover both cases, and neither
# can clobber a governed admin decision.
#
# This script does not restart the web server or the queue. Wire that into
# whatever supervises them (PM2, systemd, Docker) after this exits 0.

set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Installing dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Running migrations"
php artisan migrate --force

echo "==> Seeding configuration (adds missing keys only)"
php artisan db:seed --class=AdminConfigSeeder --force

echo "==> Rebuilding caches"
php artisan config:cache
php artisan route:cache
php artisan event:cache

echo "==> Verifying configuration registry"
php artisan config:audit

echo "==> Done. Restart php-fpm / queue workers now."
