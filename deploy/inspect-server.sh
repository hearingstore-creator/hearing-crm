#!/usr/bin/env bash
# Read-only server report: changes nothing and prints no passwords or keys.
# Paste into the Tencent Lighthouse web terminal and send back the output.
echo "== HOST"; hostname; curl -s -m 5 https://ifconfig.me; echo; lsb_release -ds 2>/dev/null; uptime -p
echo "== DISK/RAM"; df -h / | tail -1; free -h | sed -n 2p
echo "== WEB SERVER"; for s in nginx apache2 httpd; do systemctl is-active --quiet $s 2>/dev/null && echo "$s running"; done
ls /etc/php 2>/dev/null | sed 's/^/php /'; php -v 2>/dev/null | head -1
command -v bt >/dev/null && echo "aaPanel/BT panel installed"; [ -d /www/server ] && echo "/www/server exists (panel layout)"
echo "== SITES (domain -> folder)"
for f in /etc/nginx/sites-enabled/* /etc/nginx/conf.d/*.conf /www/server/panel/vhost/nginx/*.conf /etc/apache2/sites-enabled/*; do
  [ -f "$f" ] || continue
  echo "-- $f"; grep -hE '^\s*(server_name|root|ServerName|ServerAlias|DocumentRoot|listen)\b' "$f" | sed 's/^\s*/   /' | sort -u
done
echo "== SSL CERTS"; sudo ls /etc/letsencrypt/live 2>/dev/null
echo "== APPS"
for d in /var/www/* /www/wwwroot/* /home/*/public_html; do
  [ -d "$d" ] || continue
  if [ -f "$d/artisan" ]; then
    echo "-- $d (Laravel $(cd "$d" && php artisan --version 2>/dev/null | awk '{print $NF}'))"
    sudo grep -hE '^(APP_URL|APP_ENV|DB_CONNECTION|DB_HOST|DB_DATABASE)=' "$d/.env" 2>/dev/null | sed 's/^/   /'
  else echo "-- $d"; ls "$d" | head -8 | sed 's/^/   /'; fi
done
echo "== DATABASES"; sudo mysql -N -e "SELECT table_schema, ROUND(SUM(data_length+index_length)/1048576,1) AS MB FROM information_schema.tables GROUP BY table_schema" 2>/dev/null || echo "(no mysql root access via sudo)"
echo "== CRON"; (crontab -l; sudo crontab -l -u www-data) 2>/dev/null | grep -v '^#'
echo "== QUEUE WORKERS"; ls /etc/supervisor/conf.d 2>/dev/null; systemctl list-units --type=service --no-legend 2>/dev/null | grep -iE 'queue|horizon|worker' | awk '{print $1}'
echo "== DONE"
