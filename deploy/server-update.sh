#!/usr/bin/env bash
# Runs ON THE SERVER. Called by deploy/deploy.ps1 after the code bundle is uploaded to /tmp/crm-release.tgz.
# Order: backup DB -> maintenance on -> unpack code -> packages -> migrate -> caches -> worker restart -> maintenance off.
set -euo pipefail
APP=/var/www/crm
BUNDLE=/tmp/crm-release.tgz
cd "$APP"

STAMP=$(date +%F-%H%M)
sudo mkdir -p /var/backups/crm
sudo sh -c "mysqldump --single-transaction crm | gzip > /var/backups/crm/pre-deploy-$STAMP.sql.gz"
echo "Backup: /var/backups/crm/pre-deploy-$STAMP.sql.gz"

php artisan down --retry=30 || true
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

tar -xzf "$BUNDLE" -C "$APP" --no-overwrite-dir --touch --no-same-owner --no-same-permissions
rm -f "$BUNDLE"

composer install --no-dev --optimize-autoloader --no-interaction --no-progress --ignore-platform-req=php+ 2>&1 | tail -n 2
php artisan migrate --force
php artisan db:seed --class=AclSeeder --force
php artisan optimize:clear >/dev/null
php artisan optimize
php artisan view:cache >/dev/null

sudo chown -R www-data:www-data storage bootstrap/cache
sudo setfacl -R -m u:www-data:rwX,u:ubuntu:rwX storage bootstrap/cache
sudo chown ubuntu:www-data "$APP/.env" && sudo chmod 640 "$APP/.env" || true
sudo systemctl reload php8.5-fpm
php artisan queue:restart >/dev/null

php artisan up
echo "DEPLOY-OK $(date '+%F %T')"
