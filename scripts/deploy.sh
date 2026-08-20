#!/usr/bin/env bash
#
# Migrate, seed and restart the API. Run on the server, from the repo root.
#
# `~/deploy-kalaanba-api.sh` fetches and resets to origin/main, then delegates
# here. The fetch has to stay out there: a script cannot pull the version of
# itself that is about to run. Everything after the pull lives in git, where it
# is reviewable and changes with the code that needs it.
#
# Everything except the seed and the audit was already running on the box. Both
# additions are explained where they appear.

set -euo pipefail

cd "$(dirname "$0")/.."

# `php8.4`, not `php`. The box has more than one PHP and the default is not
# this one; the fpm pool is pinned to 8.4 as well.
PHP=php8.4

echo "==> Installing dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Running migrations"
# Schema changes AND config value changes. A config key that already ships and
# needs to CHANGE is a migration, because migrations run once per environment
# and are ordered. See 2026_08_19_000001_expand_player_positions_config.
$PHP artisan migrate --force

echo "==> Seeding configuration (adds missing keys only)"
# ADDED 2026-08-20. Without this, a NEW config key reaches production only if
# somebody remembers to run it by hand, and the API silently serves whatever
# compiled fallback the code carries. That is exactly what happened with
# `player.positions`: config had never been seeded, so every environment served
# four positions while the contract said thirteen.
#
# Safe on every deploy. The seeder checks existence on (key, scope, scope_id)
# and inserts only what is missing, so it never overwrites a value an admin has
# changed. It does not update existing keys either, which is why changes go
# through a migration above.
$PHP artisan db:seed --class=AdminConfigSeeder --force

echo "==> Publishing Filament assets"
$PHP artisan filament:assets

echo "==> Clearing config cache"
# `config:clear`, deliberately not `config:cache`. Kept as the box already had
# it. Both workers cache config at boot, so a .env or config change is
# invisible to them until they are recycled.
$PHP artisan config:clear

echo "==> Verifying the configuration registry"
# ADDED 2026-08-20. Fails the deploy on duplicate or malformed admin_config
# rows. Written after the seeder was found to have been inserting a complete
# second copy of every key on every run, silently, for months: reads still
# worked because Config::get takes the newest row and the copies were
# identical. The only way to catch that is to look for it on purpose.
$PHP artisan config:audit

echo "==> Reloading workers"
pm2 reload sahara-kalaanba-queue  --update-env
pm2 reload sahara-kalaanba-outbox --update-env

echo "==> kalaanba-api deploy OK: $(date -u)"
