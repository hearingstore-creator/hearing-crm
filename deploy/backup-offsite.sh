#!/usr/bin/env bash
# Daily backup with an off-server copy. Runs ON THE SERVER from root's crontab (see docs/BACKUP_AND_CRON.md).
#   1. mysqldump the CRM database to /var/backups/crm/daily-YYYY-MM-DD.sql.gz, keep the last KEEP_DAYS days locally
#   2. upload it to object storage (Tencent COS) with rclone, keep REMOTE_KEEP_DAYS there
#   3. copy the uploaded files (storage/app, public/file) to object storage, incrementally
#   4. copy .env (it holds APP_KEY) encrypted with BACKUP_PASSPHRASE, if one is set
#   5. write $STATUS_FILE so the CRM can tell the owner if a night's backup failed or stopped running
# Settings come from /etc/crm-backup.env (not in the code bundle). Exit code is non-zero if anything fails,
# so cron logs the failure, and the status file says "failed" so the CRM raises an alert.
set -Eeuo pipefail

ENV_FILE=/etc/crm-backup.env
[ -f "$ENV_FILE" ] && . "$ENV_FILE"

APP_DIR="${APP_DIR:-/var/www/crm}"
DB_NAME="${DB_NAME:-crm}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/crm}"
KEEP_DAYS="${KEEP_DAYS:-14}"
RCLONE_REMOTE="${RCLONE_REMOTE:-}"          # e.g. cos:my-bucket/crm-backups   (empty = local only, and exit 1)
REMOTE_KEEP_DAYS="${REMOTE_KEEP_DAYS:-60}"
FILES_DIRS="${FILES_DIRS:-storage/app public/file}"   # relative to APP_DIR; uploaded files the database only points to
BACKUP_PASSPHRASE="${BACKUP_PASSPHRASE:-}"            # encrypts the .env copy; empty = .env is not copied
STATUS_FILE="${STATUS_FILE:-$BACKUP_DIR/status.json}"
MIN_DUMP_BYTES="${MIN_DUMP_BYTES:-20480}"

STAMP=$(date +%F)
FILE="$BACKUP_DIR/daily-$STAMP.sql.gz"
mkdir -p "$BACKUP_DIR"
chmod 755 "$BACKUP_DIR"

DB_BYTES=0
FILES_STATUS="not run"
ENV_STATUS="not run"

# The status file is what the CRM reads (readable by the web user). Written on success and on any failure.
write_status() {
    local state="$1" detail="$2"
    printf '{"status":"%s","at":"%s","detail":"%s","db_file":"daily-%s.sql.gz","db_bytes":%s,"files":"%s","env":"%s"}\n' \
        "$state" "$(date '+%F %T')" "$detail" "$STAMP" "$DB_BYTES" "$FILES_STATUS" "$ENV_STATUS" > "$STATUS_FILE.tmp"
    chmod 644 "$STATUS_FILE.tmp"
    mv "$STATUS_FILE.tmp" "$STATUS_FILE"
}
trap 'rc=$?; echo "$(date "+%F %T") ERROR: backup failed near line $LINENO (exit $rc)" >&2; write_status failed "failed near line $LINENO (exit $rc)"' ERR

# 1. Dump (single transaction = consistent snapshot without locking tables)
mysqldump --single-transaction --routines --triggers --events "$DB_NAME" | gzip -9 > "$FILE.tmp"
gzip -t "$FILE.tmp"
DB_BYTES=$(stat -c %s "$FILE.tmp")
if [ "$DB_BYTES" -lt "$MIN_DUMP_BYTES" ]; then
    echo "$(date '+%F %T') ERROR: dump is only $DB_BYTES bytes, something is wrong." >&2
    false
fi
mv "$FILE.tmp" "$FILE"
echo "$(date '+%F %T') dump ok: $FILE ($(du -h "$FILE" | cut -f1))"

# Local retention
find "$BACKUP_DIR" -name 'daily-*.sql.gz' -mtime +"$KEEP_DAYS" -delete
find "$BACKUP_DIR" -name 'env-*.enc' -mtime +"$KEEP_DAYS" -delete

# 2. Off-server copy of the dump
if [ -z "$RCLONE_REMOTE" ]; then
    echo "$(date '+%F %T') ERROR: RCLONE_REMOTE is not set in $ENV_FILE – backup is NOT copied off the server." >&2
    false
fi
rclone copy "$FILE" "$RCLONE_REMOTE" --immutable --retries 3
rclone check "$BACKUP_DIR" "$RCLONE_REMOTE" --include "daily-$STAMP.sql.gz" --one-way
rclone delete "$RCLONE_REMOTE" --min-age "${REMOTE_KEEP_DAYS}d" --include 'daily-*.sql.gz'
echo "$(date '+%F %T') off-server copy ok: $RCLONE_REMOTE/daily-$STAMP.sql.gz"

# 3. Uploaded files (device photos, logos, attachments): the database only holds their paths.
#    "copy" is incremental and never deletes, so a file removed by mistake on the server is still in the bucket.
FILES_STATUS="ok"
for d in $FILES_DIRS; do
    if [ -d "$APP_DIR/$d" ]; then
        remote_dir="$RCLONE_REMOTE/files/$(echo "$d" | tr '/' '-')"
        rclone copy "$APP_DIR/$d" "$remote_dir" --retries 3 --exclude '*.tmp' --exclude '.gitignore'
        rclone check "$APP_DIR/$d" "$remote_dir" --one-way --exclude '*.tmp' --exclude '.gitignore'
        echo "$(date '+%F %T') files ok: $d -> $remote_dir"
    else
        echo "$(date '+%F %T') note: $APP_DIR/$d does not exist, skipped."
    fi
done

# 4. .env holds APP_KEY (needed to read encrypted values such as lead-form secrets) and passwords, so it is
#    only ever copied encrypted. Restore: openssl enc -d -aes-256-cbc -pbkdf2 -pass pass:PASSPHRASE -in env-DATE.enc -out .env
if [ -n "$BACKUP_PASSPHRASE" ] && [ -f "$APP_DIR/.env" ]; then
    export BACKUP_PASSPHRASE
    ENC="$BACKUP_DIR/env-$STAMP.enc"
    openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_PASSPHRASE -in "$APP_DIR/.env" -out "$ENC"
    chmod 600 "$ENC"
    rclone copyto "$ENC" "$RCLONE_REMOTE/env/env-$STAMP.enc" --retries 3
    rclone delete "$RCLONE_REMOTE/env" --min-age "${REMOTE_KEEP_DAYS}d" --include 'env-*.enc' || true
    ENV_STATUS="ok"
    echo "$(date '+%F %T') env copy ok (encrypted)"
else
    ENV_STATUS="skipped (no BACKUP_PASSPHRASE)"
    echo "$(date '+%F %T') note: .env not copied (set BACKUP_PASSPHRASE in $ENV_FILE to copy it encrypted)."
fi

write_status ok "all steps finished"
echo "$(date '+%F %T') backup finished"
