# Dashboard Connected Services Disclosure Fix

Date: 2026-07-17

## Scope

Fix the `/x/cockpit` Connected Services disclosure behavior so technical payload/readiness rows are not present in the default dashboard scrape.

## Change

- Replaced native `<details>` with a Vue-controlled disclosure toggle.
- Default visible card content remains:
  - source label;
  - status;
  - count;
  - `Connection details` toggle.
- Payload policy and display readiness rows are rendered only after the operator opens the relevant toggle.

## Boundary

This is presentation-only.

No read-model behavior, durable activity storage behavior, filters, lifecycle truth, execution behavior, journal writes, x-action execution, x-feedback delivery, provider calls, campaign mutation, voucher mutation, wallet movement, public API behavior, or unsafe payload exposure changed.

## Verification

- `npm run test:frontend -- CockpitDashboardHydration.test.ts CockpitDashboardFoundation.test.ts CockpitDashboardShell.test.ts CockpitLayout.test.ts CockpitReadOnlyScenarioValidation.test.ts`
- `php artisan x-change:install --force --no-interaction`
- `php artisan x-change:doctor --assets`
- `npm run build`

Result:

- 5 files passed
- 43 tests passed
- Published Cockpit assets match package source.
- Host production build passed.
- Non-blocking build warnings remain from third-party Rolldown pure-annotation parsing in `node_modules/reka-ui/node_modules/@vueuse/core`.

## UI Effect

The default `/x/cockpit` scrape should show only the scan-friendly Connected Services facts. It should not show `Payload policy`, `Display readiness`, or policy values until a `Connection details` toggle is opened.
