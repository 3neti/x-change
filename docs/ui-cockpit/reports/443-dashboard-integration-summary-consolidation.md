# Dashboard Integration Summary Consolidation

Date: 2026-07-16

## Scope

Make the `/x/cockpit` Connected Services section easier to scan by reducing visible integration-card detail.

## Change

- Kept the three service cards:
  - Journal Evidence
  - Action CTAs
  - Feedback Deliveries
- Visible card content now prioritizes:
  - operator-readable source label;
  - connection status;
  - item count.
- Moved lower-level payload policy and readiness text behind a collapsed `Connection details` disclosure.

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

The Connected Services section should read as a compact readiness summary. Detailed redaction/readiness policy remains available when the operator expands `Connection details`.
