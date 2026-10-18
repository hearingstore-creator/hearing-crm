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
# Scripts run by cron must have Unix line endings (the bundle is built on Windows)
sed -i 's/\r$//' "$APP"/deploy/*.sh

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

# Safety checks (warnings only): debug pages must be off, and the scheduler must run every minute
if grep -qiE '^APP_DEBUG=(true|1)\s*$' "$APP/.env" || ! grep -qiE '^APP_ENV=production\s*$' "$APP/.env"; then
    echo "WARNING: .env should have APP_ENV=production and APP_DEBUG=false (error pages would show code and settings to visitors)."
fi
if ! (crontab -l 2>/dev/null; sudo crontab -l -u www-data 2>/dev/null) | grep -q 'schedule:run'; then
    echo "WARNING: no cron entry for 'php artisan schedule:run' – SLA alerts, digest, reminders and backups will not run."
    echo "         Add: * * * * * cd $APP && php artisan schedule:run >> /dev/null 2>&1"
fi

echo "DEPLOY-OK $(date '+%F %T')"
