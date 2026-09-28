# SaaS PWA MVP

## What ships

- Public, account-free booking at `/b/{business-slug}`.
- Tenant-specific service, staff, availability, branding, confirmation, manifest, icon and install destination.
- Mobile business workspace at `/app/{business-slug}` with today's summary, agenda, confirmation, cancellation, rescheduling, service hours/pricing, staff availability and sharing.
- Optional install prompt only after customer confirmation; iOS Safari receives non-blocking Add to Home Screen guidance.
- Two visually distinct demo tenants from `BusinessSeeder` + `BarbershopDemoSeeder`.
- One-pass first-customer provisioning for business identity, logo, weekly hours, owner, launch services and staff.

## Local demo

```bash
php artisan migrate:fresh --seed
php artisan serve --host=0.0.0.0 --port=8000
```

Open:

- Customer: `http://127.0.0.1:8000/b/barberia-tres-amigos`
- Second tenant: `http://127.0.0.1:8000/b/salon-aurora`
- Business: `http://127.0.0.1:8000/app/barberia-tres-amigos`
- Login: `barbershop.owner@example.com` / `password`

Localhost is accepted by browsers as a secure development context. A phone using a LAN IP can test the booking flow, but production-like PWA installation requires HTTPS.

## Production requirements

- Set `APP_ENV=production`, `APP_DEBUG=false`, a strong `APP_KEY`, and the real HTTPS `APP_URL`.
- Terminate TLS at the web server/proxy and forward the original scheme so Laravel generates HTTPS manifest, share and signed-confirmation URLs.
- Serve `/service-worker.js` from the origin root with `application/javascript`; do not redirect it to login.
- Keep `/b/*`, tenant manifests and icons public. Keep `/app/*` behind session authentication.
- Configure persistent database/cache drivers. Shared Redis locks are recommended when running multiple application instances.
- Replace the external QR image endpoint with a locally generated QR before operating in a restricted/offline network.
- Do not run demo seed classes in production. `DatabaseSeeder` skips demo tenants and known-password users outside `local` and `testing`.
- Run `php artisan storage:link` so uploaded tenant logos can be displayed and embedded in tenant PWA icons.

## First-customer onboarding

- [15-minute onboarding runbook](FIRST_BARBERSHOP_ONBOARDING.md)
- [Real-device verification](REAL_DEVICE_VERIFICATION.md)

The Super Admin creation wizard now creates a bookable catalog and team together with the tenant. Its completion workspace exposes the exact customer booking URL and owner PWA URL for handoff.

## Release smoke test

1. Open each tenant's `/b/{slug}` URL in a clean mobile browser and confirm no data crosses between them.
2. Select service → staff → date → time → customer details → review → confirm without logging in.
3. Confirm the slot disappears for that staff member but remains available for another eligible staff member.
4. Log in to `/app/{slug}`, confirm the new booking appears only there, then confirm, reschedule and cancel it.
5. Use Copy, native Share and QR from the Share tab.
6. On Android/Chrome, install the business workspace and verify standalone launch.
7. On iPhone/Safari, use Share → Add to Home Screen and verify the correct tenant reopens.
8. Complete another public booking and verify the optional installation suggestion appears only after confirmation and can be ignored.

Automated validation: `php artisan test --compact`.
