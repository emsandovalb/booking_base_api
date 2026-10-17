# Database backups

The booking data (businesses, staff, services, appointments, users) lives in one Postgres database. Losing it means losing every business's agenda, so backups need to happen without anyone remembering to run them, and the restore needs to have been tried at least once.

## 1. Use the provider's backups first

On Render, paid Postgres plans include automatic daily backups and point-in-time recovery; free instances have **no backups** and are deleted after their trial period. Before the first real customer:

1. Move the database to a paid Render Postgres plan (or another managed Postgres with automatic backups).
2. In the database's **Recovery** tab, confirm backups are listed and note the retention period.

## 2. Keep an independent copy

Provider backups disappear with the provider account. Keep a second copy elsewhere with `scripts/backup-db.sh`:

```bash
DB_URL="postgres://user:pass@host:5432/dbname" \
BACKUP_S3_URI="s3://my-bucket/booking-db" \
AWS_ACCESS_KEY_ID=... AWS_SECRET_ACCESS_KEY=... \
scripts/backup-db.sh
```

- It writes `backups/booking-db-<UTC timestamp>.dump` (compressed `pg_dump` custom format), checks the file is readable, uploads it when `BACKUP_S3_URI` is set, and keeps the newest 7 local dumps.
- For Cloudflare R2 or Backblaze B2 also set `AWS_ENDPOINT_URL`.
- Use the database's **external** connection URL when running outside Render.
- `pg_dump` must be the same major version as the server or newer.
- Set a lifecycle rule on the bucket (for example keep 30 daily dumps).

Run it once a day: a Render Cron Job using this repo's Docker image, a scheduled GitHub Actions workflow with the URL and keys as secrets, or `crontab` on any machine with `pg_dump`.

## 3. Test the restore every month

A backup only counts once it has been restored. Restore the latest dump into an **empty** scratch database and check the numbers look right:

```bash
createdb booking_restore_drill            # or create a new empty Render database
scripts/restore-db.sh backups/booking-db-<timestamp>.dump \
  "postgres://user:pass@host:5432/booking_restore_drill"
```

The script prints the number of migrations, businesses and bookings it restored. Compare them with production, then drop the scratch database. It refuses to restore into a database that already has tables unless `RESTORE_INTO_NON_EMPTY=yes` is set, so it can't overwrite production by accident.

Both scripts were tested end to end against Postgres 16 with the seeded demo data: backup, restore into an empty database, matching counts, and the refusal on a non-empty target.

## 4. If production data is lost

1. Put the app in maintenance mode: `php artisan down`.
2. Prefer the provider's point-in-time recovery to just before the incident.
3. Otherwise create a new empty database, restore the newest good dump with `scripts/restore-db.sh`, and point `DB_URL` at it.
4. Run `php artisan migrate --force` (applies anything newer than the dump), then `php artisan up`.
5. Tell affected businesses which time window may need re-entering.
