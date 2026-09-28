# First barber shop onboarding

This runbook is the production handoff path for the first paying barber shop. It uses the existing Super Admin wizard and owner PWA; no demo records or manual database edits are required.

## Before the call (2 minutes)

Collect these items in one message or form:

- Public business name and preferred URL slug.
- Square logo in PNG, JPG, or WebP format, no larger than 2 MB.
- Phone, WhatsApp, email, address, city, and country.
- Weekly opening and closing hours, including closed days.
- Up to four launch services: name, customer price, and duration.
- Up to four launch professionals: name and optional phone.
- Owner's name and email. Generate a unique temporary password; do not reuse a demo password.

Confirm the production origin uses HTTPS and that `APP_URL` is the exact public origin. Booking, manifest, signed confirmation, and share URLs are generated from it.

## One-time production preparation

```bash
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Create the first platform administrator through the protected bootstrap seeder and environment variables documented in `.env.example`. Do not run local demo seeders in production. The default `DatabaseSeeder` now skips known-password users and demo tenants outside `local` and `testing`.

## Configure the business (8 minutes)

1. Open **Super Admin → Businesses → Create business**.
2. **Business:** enter the real name, choose `barbershop`, confirm the slug, and leave the tenant active.
3. **Brand:** replace the generic app names with the shop's names, upload the real logo, and set the three brand colors.
4. **Contact:** enter the public phone/WhatsApp and exact location. Leave unused channels blank.
5. **Hours:** mark every closed day and confirm opening/closing times. These day-specific hours control public availability.
6. **Launch setup:** enter the real service prices/durations and professional names. Blank extra rows are ignored. Initial professionals are linked to every launch service; refine assignments later only if necessary.
7. **Owner:** enter the real owner email and a temporary password of at least eight characters.
8. **Features:** keep the booking, staff, profile, and dashboard defaults enabled.
9. **Review:** verify the service/staff counts and press **Finish** once.

The transaction creates the tenant, owner membership, services, staff, service assignments, working-hours metadata, and branding together. A failed creation does not leave a partial tenant, and an uploaded logo is removed if provisioning fails.

## Handoff (5 minutes)

The resulting workspace displays a **First-customer handoff** card.

1. Copy the **Customer booking** URL and open it in a private mobile browser.
2. Complete one real test booking with the owner watching.
3. Copy the **Owner PWA** URL, sign in as the owner, and verify the test appointment appears.
4. Open **More** in the owner PWA. Test Copy, native Share, and the QR code.
5. Install the owner PWA and launch it once from the home screen.
6. Confirm or cancel the test booking. Delete no production configuration during the test.
7. Give the owner the customer URL, QR, owner URL, and temporary credentials through a secure channel. Ask the owner to replace the temporary password through the agreed account process.

## Ready-to-hand-off checklist

- [ ] Correct business name and slug.
- [ ] Real logo and brand colors; no other tenant's imagery.
- [ ] Correct contact details and address.
- [ ] Every open/closed day verified.
- [ ] Every launch service has the correct customer price and duration.
- [ ] Every active professional is present and assigned to the correct services.
- [ ] Public booking completes without login or installation.
- [ ] Booking appears only in the correct owner agenda.
- [ ] Confirm, reschedule, and cancel actions work.
- [ ] Public link copy, native Share, and QR work.
- [ ] Owner PWA launches in standalone mode from the phone home screen.
- [ ] Temporary/demo booking is clearly identified and handled after verification.

If any item fails, do not hand off the link. Suspend only the affected tenant while correcting it; never reuse another tenant as a shortcut.
