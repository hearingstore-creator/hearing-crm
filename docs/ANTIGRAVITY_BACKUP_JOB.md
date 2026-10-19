# Job prompt for Antigravity: set up and verify the nightly backup on the live server

Copy everything below the line into Antigravity.

---

You have SSH access to the live server of the Hearing Store CRM (Ubuntu, Tencent Cloud, app in `/var/www/crm`, database `crm`, web user `www-data`, deploy user `ubuntu` with sudo). Your job is to install, run and verify the nightly backup, then report. Follow the steps in order and do not skip the checks.

## What this is for

The CRM code already contains a nightly backup script, `/var/www/crm/deploy/backup-offsite.sh`. Each run:
1. dumps the database to `/var/backups/crm/daily-YYYY-MM-DD.sql.gz` and copies it to Tencent COS with rclone,
2. copies the uploaded files (`storage/app`, `public/file`) to COS incrementally,
3. copies `.env` to COS encrypted with a passphrase (`BACKUP_PASSPHRASE`),
4. writes `/var/backups/crm/status.json`, which the CRM reads each morning (`php artisan crm:backup-check`) to alert the owner if a backup failed or stopped.

The full manual is `/var/www/crm/docs/BACKUP_AND_CRON.md`. Read it first.

## Hard rules

- Do NOT change application code, run migrations, run `migrate:fresh` or any seeder, restart services, or edit `/var/www/crm/.env`.
- Do NOT touch the live database `crm` except to read it (mysqldump, SELECT COUNT). Any restore test goes into a NEW scratch database named `crm_restore_test`, which you drop afterwards.
- Do NOT delete anything from `/var/backups/crm` or the COS bucket.
- Do NOT print secrets (rclone keys, DB passwords, the contents of `.env`) in your output. The only secret you may show is the backup passphrase you generate in step 3, once, in the final report.
- If a step fails, stop, show the exact error and your diagnosis, and do not work around it by removing safety flags (`--single-transaction`, `--routines`, `--triggers`, `--events`, the size check) or by editing the script. Ask me instead.

## Steps

### 0. Make sure the new code is on the server
```bash
grep -c "FILES_DIRS" /var/www/crm/deploy/backup-offsite.sh
ls /var/www/crm/app/Console/Commands/BackupCheck.php
```
Both must succeed (count 1 or more, file exists). If not, STOP: the latest version has not been deployed. Tell me to run `deploy/deploy.ps1` from my PC. Do not deploy it yourself.

### 1. Look at what exists
Report the output of each, hiding secrets:
```bash
sudo ls -la /etc/crm-backup.env && sudo sed -E 's/(KEY|SECRET|PASSPHRASE)=.*/\1=***hidden***/' /etc/crm-backup.env
which rclone mysqldump openssl
sudo rclone listremotes
sudo crontab -l
sudo crontab -l -u www-data
ls -lh /var/backups/crm | tail -10
df -h /var/backups /var/www
```
If `rclone` or the `cos` remote or `RCLONE_REMOTE` in `/etc/crm-backup.env` is missing, follow section 2 of `BACKUP_AND_CRON.md` as far as you can. For anything that needs my Tencent COS keys or bucket name, STOP and ask me for them; do not invent values.

### 2. Settings file
`/etc/crm-backup.env` must be owned by root, mode 600, and contain at least `DB_NAME=crm`, `RCLONE_REMOTE=cos:<bucket>/crm-backups`, `KEEP_DAYS=14`, `REMOTE_KEEP_DAYS=60`. Fix ownership and mode if needed.

### 3. Encryption passphrase for the .env copy
If `BACKUP_PASSPHRASE` is not already in the file, generate one and append it:
```bash
PASS=$(openssl rand -base64 32)
echo "BACKUP_PASSPHRASE=$PASS" | sudo tee -a /etc/crm-backup.env >/dev/null
sudo chmod 600 /etc/crm-backup.env
```
Keep `$PASS` only for the final report (I will store it in a password manager). If a passphrase already exists, do not change it and do not print it.

### 4. Run the backup by hand
```bash
sudo bash /var/www/crm/deploy/backup-offsite.sh
```
It must end with `backup finished`. Capture the whole output. Then verify:
```bash
cat /var/backups/crm/status.json                       # status must be "ok"
ls -lh /var/backups/crm | tail -5                      # today's daily-*.sql.gz and env-*.enc exist
sudo rclone ls cos:<bucket>/crm-backups | tail -20     # dump, files/..., env/env-DATE.enc are in the bucket
sudo rclone size cos:<bucket>/crm-backups/files        # size of the files copy
```
Use the real bucket path from `RCLONE_REMOTE`.

### 5. Schedule it
Root's crontab must contain exactly one line like this (add it without removing other lines if it is missing):
```
30 2 * * * bash /var/www/crm/deploy/backup-offsite.sh >> /var/log/crm-backup.log 2>&1
```
`www-data`'s crontab must contain the Laravel scheduler line (add it if missing, keeping other lines):
```
* * * * * cd /var/www/crm && php artisan schedule:run >> /dev/null 2>&1
```
Show both crontabs afterwards.

### 6. Check the alert
```bash
cd /var/www/crm && sudo -u www-data php artisan crm:backup-check
```
Expected: `Backup OK: last run ... database ... files ok, env ok`. If it says the status file cannot be found or read, check that `/var/backups/crm` is mode 755 and `status.json` is mode 644 (the web user must be able to read it) and fix only those permissions.

### 7. Prove the backup restores
Use the newest daily dump. Never restore into `crm`.
```bash
sudo mysql -e 'CREATE DATABASE crm_restore_test CHARACTER SET utf8mb4'
gunzip -c /var/backups/crm/daily-$(date +%F).sql.gz | sudo mysql crm_restore_test
for t in users customers leads sale_masters sale_details purchase_masters ledgers; do
  echo -n "$t live: "; sudo mysql -N -e "SELECT COUNT(*) FROM crm.$t"
  echo -n "$t restored: "; sudo mysql -N -e "SELECT COUNT(*) FROM crm_restore_test.$t"
done
sudo mysql -e 'DROP DATABASE crm_restore_test'
```
Counts may differ by a few rows if people worked during the minutes between the backup and the check; explain any difference larger than that.

Then prove the `.env` copy decrypts, without showing its content:
```bash
sudo rclone copyto cos:<bucket>/crm-backups/env/env-$(date +%F).enc /tmp/env-check.enc
sudo bash -c 'set -a; . /etc/crm-backup.env; set +a; openssl enc -d -aes-256-cbc -pbkdf2 -pass env:BACKUP_PASSPHRASE -in /tmp/env-check.enc -out /tmp/env-check.txt && grep -c "^APP_KEY=" /tmp/env-check.txt'
sudo shred -u /tmp/env-check.enc /tmp/env-check.txt
```
The grep must print `1`.

### 8. Final report
Give me a short table: each step, pass or fail, and the key numbers (dump size, files size in the bucket, restored row counts, status.json contents without secrets). Then:
- the backup passphrase (only if you generated it), with a note that I must save it now,
- anything you could not do and why,
- anything that looked wrong (disk space, old files, cron missing, warnings in the output).
