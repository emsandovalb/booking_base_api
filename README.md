# Bemuss Booking SaaS

Laravel booking platform with tenant-isolated public booking pages and installable owner PWAs.

## Product routes

- Customer booking: `/b/{business-slug}` — public, no account or installation required.
- Owner PWA: `/app/{business-slug}` — authenticated business agenda and management.
- Super Admin onboarding: `/super-admin/businesses/create`.
- Tenant manifest: `/app/{business-slug}/manifest.webmanifest`.
- Service worker: `/service-worker.js`.

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

Local demo credentials and URLs are documented in [docs/SAAS_PWA_MVP.md](docs/SAAS_PWA_MVP.md). Demo users, businesses, bookings, and known passwords are seeded only in `local` and `testing`; `DatabaseSeeder` skips them in every other environment.

To configure the guarded repository-local QA logins without resetting the database, follow [docs/LOCAL_QA_CREDENTIALS.md](docs/LOCAL_QA_CREDENTIALS.md).

### Flutter Web CORS

Flutter Web development uses a dynamic browser port. For repository-local QA, set `CORS_ALLOW_LOCALHOST=true` to allow only `http://localhost:<port>` and `http://127.0.0.1:<port>` origins. Keep that flag false in deployed environments.

Production browser origins must be listed explicitly with `FRONTEND_URL` or a comma-separated `FRONTEND_URLS`; unrestricted `*` origins are not used. After changing CORS environment values in a cached environment, run `php artisan config:clear` during development or rebuild the production configuration cache.

## First real customer

Use the [15-minute barber shop onboarding runbook](docs/FIRST_BARBERSHOP_ONBOARDING.md). The current wizard provisions, in one transaction:

- Tenant and owner membership.
- Business identity, uploaded logo, colors, and contact information.
- Day-specific working hours.
- Initial services, prices, and durations.
- Initial staff and service assignments.
- Tenant-specific public booking and owner PWA handoff URLs.

Complete [real-device verification](docs/REAL_DEVICE_VERIFICATION.md) on the production HTTPS origin before handing the links to the owner.

## Production baseline

Set `APP_ENV=production`, `APP_DEBUG=false`, a strong `APP_KEY`, and the exact HTTPS `APP_URL`. Configure persistent database/cache drivers, trusted proxy forwarding, mail delivery, backups, and HTTPS before onboarding.

```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan test
```

Do not run demo seed classes in production. Create the initial platform administrator with `BootstrapAdminSeeder` and environment-provided credentials as described in `.env.example`.

## Validation

```bash
vendor/bin/pint --test
npm run build
php artisan test
```

See [docs/SAAS_PWA_MVP.md](docs/SAAS_PWA_MVP.md) for architecture, installability requirements, and the complete smoke test.
