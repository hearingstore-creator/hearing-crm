# Server setup: daily off-server backup and the scheduler

One-time setup on the Tencent server (Ubuntu). Run the commands as the `ubuntu` user with `sudo`.

## 1. Scheduler (automations need it)

SLA alerts, the 8 AM digest, re-engagement, appointment reminders, warranty and points jobs, and
appointment-hold clean-up run only through Laravel's scheduler. Check that it is installed:

```bash
sudo crontab -l -u www-data | grep schedule:run
```

If nothing is printed, add it:

```bash
(sudo crontab -l -u www-data 2>/dev/null; echo '* * * * * cd /var/www/crm && php artisan schedule:run >> /dev/null 2>&1') | sudo crontab -u www-data -
```

`deploy/server-update.sh` now prints a warning after every deploy when this entry is missing.

## 2. Daily backup copied off the server

Backups in `/var/backups/crm` are lost with the server, so `deploy/backup-offsite.sh` also copies
each daily dump to Tencent COS (object storage).

1. In the Tencent Cloud console, create a **private** COS bucket (e.g. `crm-backups-...`) in the same
   region, and an API key (SecretId / SecretKey) that can only write to that bucket.
2. Install rclone and add the bucket as a remote named `cos`:

   ```bash
   sudo apt-get install -y rclone
   sudo rclone config
   ```

   Choose: `n` (new remote) → name `cos` → storage `s3` → provider `TencentCOS` → enter the
   SecretId / SecretKey → endpoint for your region (e.g. `cos.ap-singapore.myqcloud.com`) → ACL `private`.
3. Create `/etc/crm-backup.env` (readable by root only):

   ```bash
   sudo tee /etc/crm-backup.env >/dev/null <<'EOF'
   DB_NAME=crm
   RCLONE_REMOTE=cos:YOUR-BUCKET-NAME/crm-backups
   KEEP_DAYS=14
   REMOTE_KEEP_DAYS=60
   EOF
   sudo chmod 600 /etc/crm-backup.env
   ```

4. Run it once by hand and check that it ends with `off-server copy ok`:

   ```bash
   sudo bash /var/www/crm/deploy/backup-offsite.sh
   ```

5. Schedule it daily at 02:30 (root's crontab, because mysqldump uses root's MySQL login):

   ```bash
   (sudo crontab -l 2>/dev/null; echo '30 2 * * * bash /var/www/crm/deploy/backup-offsite.sh >> /var/log/crm-backup.log 2>&1') | sudo crontab -
   ```

## 3. Test a restore (every few months)

A backup is only proven when it restores. Into a scratch database, never the live one:

```bash
sudo mysql -e 'CREATE DATABASE crm_restore_test'
gunzip -c /var/backups/crm/daily-YYYY-MM-DD.sql.gz | sudo mysql crm_restore_test
sudo mysql -e 'SELECT COUNT(*) FROM crm_restore_test.sale_masters'
sudo mysql -e 'DROP DATABASE crm_restore_test'
```

## 4. Production settings

The live `/var/www/crm/.env` must have `APP_ENV=production` and `APP_DEBUG=false`. With debug on,
error pages show code and settings to anyone. The deploy script warns when this is wrong.
