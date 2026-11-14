#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")"
test -f vendor/autoload.php || composer install --no-interaction --prefer-dist
test -f .env || cp .env.example .env
test -f database/database.sqlite || touch database/database.sqlite
grep -q '^APP_KEY=base64:.' .env || php artisan key:generate --force
php artisan config:clear
php artisan migrate --force
php artisan db:seed --force
if [ "${1:-}" = '--demo' ]; then php artisan db:seed --class=DemoSeeder --force; fi
php artisan storage:link
if [ ! -f public/build/manifest.json ]; then npm ci --ignore-scripts --no-audit --no-fund; npm run build; fi
php artisan treefund:doctor
printf '\nTreeFund siap. Isi kunci Sandbox di .env, lalu jalankan php artisan serve.\n'
