# Local QA credentials

This setup is only for the repository-local SQLite QA database. It is idempotent and does not run `DatabaseSeeder`, recreate the database, attach the Super Admin to a tenant, or touch the Tres Amigos users and memberships.

The seeder refuses to run unless all of these protections pass:

- It is explicitly enabled for the current command with `LOCAL_QA_CREDENTIALS_SETUP=true`.
- The active database driver is SQLite.
- The SQLite file resolves inside this project's `database` directory.
- JC Studio, its existing owner, and that owner's active `owner` membership already exist.

## Run from PowerShell

The current local `.env` identifies itself as production, so Laravel also requires `--force`. The QA-specific opt-in is deliberately temporary and is removed from the shell immediately afterward.

```powershell
$env:LOCAL_QA_CREDENTIALS_SETUP = 'true'
php artisan db:seed --class=LocalQaCredentialsSeeder --force
Remove-Item Env:LOCAL_QA_CREDENTIALS_SETUP
```

Do not add `LOCAL_QA_CREDENTIALS_SETUP` to `.env`. Do not run this command against a shared or production database.

## Result

| Surface | Email | QA password | Access |
|---|---|---|---|
| Bemuss Super Admin Web | `admin@bemuss.local` | `Bemuss123!` | `/login` then `/super-admin` |
| JC Studio Flutter/API | `macchie.23@gmail.com` | `Password123!` | `POST /api/v1/auth/login` with `X-Business-Slug: jc-studio` |
| Tres Amigos Flutter/API | `barbershop.owner@example.com` | `password` | Preserved; `X-Business-Slug: barberia-tres-amigos` |
| Tres Amigos Flutter/API | `demo@example.com` | `password` | Preserved; `X-Business-Slug: barberia-tres-amigos` |

The credentials live only in this explicitly invoked QA seeder, never in runtime authentication code.
