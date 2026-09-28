# Real-device verification

Run this on the actual production HTTPS origin. A LAN IP is sufficient for browser-flow checks, but it is not a reliable substitute for installability because PWA installation and service workers require a secure context outside localhost.

## Test devices

Use at least:

- One current Android phone with Chrome.
- One current iPhone with Safari.
- Mobile data or a network outside the development machine for at least one pass.

## Customer flow

1. Open `/b/{slug}` from the exact link that will be placed in WhatsApp or social profiles.
2. Confirm the business name, logo, services, prices, staff, and contact information.
3. Select a service and professional.
4. Check one open day and one configured closed day. The closed day must return no slots.
5. Select a slot, enter only name and phone, review, and confirm.
6. Confirm the success screen appears without account creation or installation.
7. Ignore the optional customer install suggestion and verify the booking remains confirmed.

## Owner flow

1. Open `/app/{slug}` and sign in with the real owner account.
2. Confirm the new booking appears under the correct tenant only.
3. Open its details and verify customer, phone, service, staff, date, time, duration, price, and status.
4. Confirm it, reschedule it to another available slot, and cancel it.
5. Verify the agenda remains readable at a 390 × 844-class viewport with no horizontal scrolling.
6. Open Services and Staff; verify the launch catalog and assignments.
7. Open More; test public link copy, native Share, public-page launch, and QR scanning with a second phone.

## Installation

### Android / Chrome

1. Use the browser's **Install app** action or the in-app install prompt when available.
2. Confirm the icon and business-specific app name.
3. Launch from the home screen.
4. Confirm it opens `/app/{slug}` in standalone mode and preserves authenticated access according to normal session lifetime.

### iPhone / Safari

1. Use **Share → Add to Home Screen**.
2. Confirm the business-specific name and icon.
3. Launch from the home screen and confirm the correct tenant route.
4. Verify safe-area spacing and bottom navigation around the home indicator.

## Technical verification

```bash
curl -I https://your-domain.example/service-worker.js
curl -I https://your-domain.example/app/your-slug/manifest.webmanifest
curl -I https://your-domain.example/pwa/your-slug/icon-192.svg
php artisan test
```

Expected results:

- HTTPS responses have no redirect loop or mixed content.
- Service worker returns HTTP 200 and `application/javascript`.
- Manifest returns HTTP 200, `display: standalone`, and the correct tenant `start_url`.
- Both manifest icons return HTTP 200.
- Browser console has no uncaught errors, failed same-origin assets, or service-worker registration failures.
- The complete automated suite passes.

Record the device model, OS/browser version, production URL, date, tester, and pass/fail result before owner handoff.
