#!/usr/bin/env bash
# Dumps the Postgres database to a compressed, timestamped file and, when
# BACKUP_S3_URI is set, uploads it to S3-compatible storage (AWS S3,
# Cloudflare R2, Backblaze B2...). Meant to run once a day from a cron job
# (Render Cron Job, GitHub Actions schedule, or crontab).
#
# Required:
#   DB_URL             postgres://user:pass@host:5432/dbname
#                      (or DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD)
# Optional:
#   BACKUP_DIR         local folder for dump files (default: ./backups)
#   BACKUP_S3_URI      e.g. s3://my-bucket/booking-db; needs the aws CLI
#   AWS_ENDPOINT_URL   for R2/B2 and other non-AWS providers
#   BACKUP_KEEP_LOCAL  how many local dumps to keep (default: 7)
set -euo pipefail

backup_dir="${BACKUP_DIR:-./backups}"
keep_local="${BACKUP_KEEP_LOCAL:-7}"

if [[ -n "${DB_URL:-}" ]]; then
  target=("$DB_URL")
else
  : "${DB_DATABASE:?Set DB_URL or DB_DATABASE}"
  export PGPASSWORD="${DB_PASSWORD:-}"
  target=(--host "${DB_HOST:-127.0.0.1}" --port "${DB_PORT:-5432}"
          --username "${DB_USERNAME:-postgres}" "$DB_DATABASE")
fi

mkdir -p "$backup_dir"
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
file="$backup_dir/booking-db-$stamp.dump"

# Custom format is compressed and lets pg_restore pick tables or run in
# parallel. --no-owner/--no-acl make the dump restorable into a database
# owned by a different user (a new Render instance, a local machine).
pg_dump --format=custom --no-owner --no-acl --file="$file" "${target[@]}"

# A dump that can't be listed is a failed backup, not a backup.
pg_restore --list "$file" > /dev/null
echo "Backup written: $file ($(du -h "$file" | cut -f1))"

if [[ -n "${BACKUP_S3_URI:-}" ]]; then
  endpoint=()
  [[ -n "${AWS_ENDPOINT_URL:-}" ]] && endpoint=(--endpoint-url "$AWS_ENDPOINT_URL")
  aws s3 cp "${endpoint[@]}" "$file" "${BACKUP_S3_URI%/}/$(basename "$file")"
  echo "Uploaded to ${BACKUP_S3_URI%/}/$(basename "$file")"
fi

# Keep only the newest local dumps; remote retention is set on the bucket.
ls -1t "$backup_dir"/booking-db-*.dump 2>/dev/null | tail -n +"$((keep_local + 1))" | xargs -r rm --
