# Cockpit Wave 54C — Pay Code Detail Full URL Presentation

## Status

Completed on 2026-07-12.

## Scope

Wave 54C renders the canonical beneficiary Pay Code URL on the Cockpit Voucher Detail page from the existing read-only `distribution_links` read model.

## Implemented

- Added `distribution_links` to the Cockpit Voucher Detail TypeScript read-model contract.
- Rendered a `Beneficiary Pay Code URL` card on Voucher Detail when read-model links are available.
- Displayed both:
  - the absolute beneficiary claim URL
  - the relative beneficiary claim path
- Kept the panel explicitly read-only with delivery disabled.
- Added frontend coverage for the rendered URL, path, payload policy, and link `href`.

## Boundaries

This slice does not:

- send SMS, email, webhook, or in-app feedback
- dispatch campaign delivery
- create short links
- generate QR assets
- write journal entries
- execute actions
- call providers
- mutate vouchers
- move money

## Verification

- `npm run test:frontend -- tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts`
- `php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave54cPayCodeDetailFullUrlPresentationTest.php`

## Next

Cockpit Wave 54D — Distribution Workspace Full URL Presentation.
