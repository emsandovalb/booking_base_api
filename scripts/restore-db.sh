#!/usr/bin/env bash
# Restores a dump made by backup-db.sh into a database.
#
#   scripts/restore-db.sh <dump-file> <target-db-url>
#
# The target should be an EMPTY database (a fresh Render instance, or a
# scratch database for the monthly restore drill). Restoring over a live
# database replaces its data, so the script refuses unless
# RESTORE_INTO_NON_EMPTY=yes is set.
set -euo pipefail

dump="${1:?Usage: restore-db.sh <dump-file> <target-db-url>}"
target="${2:?Usage: restore-db.sh <dump-file> <target-db-url>}"

tables="$(psql "$target" -Atc "select count(*) from information_schema.tables where table_schema = 'public'")"
if [[ "$tables" != "0" && "${RESTORE_INTO_NON_EMPTY:-}" != "yes" ]]; then
  echo "Target database already has $tables tables; refusing to overwrite." >&2
  echo "Use an empty database, or set RESTORE_INTO_NON_EMPTY=yes." >&2
  exit 1
fi

pg_restore --no-owner --no-acl --clean --if-exists --exit-on-error \
  --dbname="$target" "$dump"

echo "Restored $dump"
psql "$target" -Atc "select 'migrations: ' || count(*) from migrations" || true
psql "$target" -Atc "select 'businesses: ' || count(*) from businesses" || true
psql "$target" -Atc "select 'bookings: ' || count(*) from bookings" || true
