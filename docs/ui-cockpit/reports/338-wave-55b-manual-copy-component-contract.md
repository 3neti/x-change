# Cockpit Wave 55B — Manual Copy Component Contract

## Status

Completed on 2026-07-12.

## Scope

Wave 55B introduces a reusable browser-local manual copy component for operator-facing beneficiary URLs.

## Implemented

- Added `CockpitManualCopyButton`.
- The component copies a supplied string through `navigator.clipboard.writeText`.
- The component renders local UI states:
  - idle
  - copied
  - failed
  - unavailable
- The component disables copy when no URL value is available.
- Frontend tests prove copying does not call `fetch`.

## Boundaries

The component does not:

- call backend endpoints
- persist copy events
- send feedback
- dispatch campaigns
- write journal entries
- execute actions
- call providers
- mutate vouchers
- move money

## Verification

- `npm run test:frontend -- tests/frontend/cockpit/CockpitManualCopyButton.test.ts`
- `php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave55bManualCopyComponentContractTest.php`

## Next

Cockpit Wave 55C — Voucher Detail Manual Copy Adoption.
