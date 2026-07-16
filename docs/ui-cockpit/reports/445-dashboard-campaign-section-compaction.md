# Dashboard Campaign Section Compaction

Date: 2026-07-17

## Scope

Reduce default `/x/cockpit` dashboard noise in the Campaigns section.

## Change

- Kept the primary campaign summary visible:
  - campaign name or disconnected state;
  - recipient count or `No campaign selected`;
  - planning/execution context when available.
- Added a `Campaign details` toggle.
- Moved lower-level surfaces, workspace panels, operator actions, and mutation status behind the toggle.
- Kept read-only Quick Generate / Explorer links visible when campaign context is connected.

## Boundary

This is presentation-only.

No campaign mutation, campaign dispatch, Pay Code generation behavior, read-model behavior, lifecycle truth, journal writes, x-action execution, x-feedback delivery, provider calls, voucher mutation, wallet movement, public API behavior, execution behavior, or unsafe payload exposure changed.

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

The default dashboard should no longer show empty Campaign workspace/action/mutation internals. Operators can expand `Campaign details` when they need the lower-level diagnostic facts.
