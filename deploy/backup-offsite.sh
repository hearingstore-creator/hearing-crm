#!/usr/bin/env bash
# Daily database backup with an off-server copy. Runs ON THE SERVER from root's crontab (see docs/BACKUP_AND_CRON.md).
#   1. mysqldump the CRM database to /var/backups/crm/daily-YYYY-MM-DD.sql.gz
#   2. keep the last KEEP_DAYS days locally
#   3. upload the file to object storage (Tencent COS) with rclone, and keep REMOTE_KEEP_DAYS there
# Settings come from /etc/crm-backup.env (not in the code bundle). Exit code is non-zero if anything fails,
# so cron mails / logs the failure.
set -euo pipefail

ENV_FILE=/etc/crm-backup.env
[ -f "$ENV_FILE" ] && . "$ENV_FILE"

DB_NAME="${DB_NAME:-crm}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/crm}"
KEEP_DAYS="${KEEP_DAYS:-14}"
RCLONE_REMOTE="${RCLONE_REMOTE:-}"          # e.g. cos:my-bucket/crm-backups   (empty = local only, and exit 1)
REMOTE_KEEP_DAYS="${REMOTE_KEEP_DAYS:-60}"

STAMP=$(date +%F)
FILE="$BACKUP_DIR/daily-$STAMP.sql.gz"
mkdir -p "$BACKUP_DIR"

# 1. Dump (single transaction = consistent snapshot without locking tables)
mysqldump --single-transaction --routines --triggers "$DB_NAME" | gzip -9 > "$FILE.tmp"
gzip -t "$FILE.tmp"
mv "$FILE.tmp" "$FILE"
echo "$(date '+%F %T') dump ok: $FILE ($(du -h "$FILE" | cut -f1))"

# 2. Local retention
find "$BACKUP_DIR" -name 'daily-*.sql.gz' -mtime +"$KEEP_DAYS" -delete

# 3. Off-server copy
if [ -z "$RCLONE_REMOTE" ]; then
    echo "$(date '+%F %T') ERROR: RCLONE_REMOTE is not set in $ENV_FILE – backup is NOT copied off the server." >&2
    exit 1
fi
rclone copy "$FILE" "$RCLONE_REMOTE" --immutable --retries 3
rclone check "$BACKUP_DIR" "$RCLONE_REMOTE" --include "daily-$STAMP.sql.gz" --one-way
rclone delete "$RCLONE_REMOTE" --min-age "${REMOTE_KEEP_DAYS}d" --include 'daily-*.sql.gz'
echo "$(date '+%F %T') off-server copy ok: $RCLONE_REMOTE/daily-$STAMP.sql.gz"
