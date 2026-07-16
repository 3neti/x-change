# Dashboard System Activity Copy Polish

Date: 2026-07-17

## Scope

Make the dashboard activity feed label broader and less settlement-specific.

## Change

- Renamed `Settlement Activity` to `System Activity`.
- Renamed `Recent settlement events` to `Recent operating evidence`.
- Added a short read-only boundary sentence explaining that the panel does not execute follow-up work.
- Changed projection source copy from `Evidence sources` to `Read from`.

## Boundary

This is presentation-only.

No read-model behavior, durable activity storage behavior, lifecycle truth, execution behavior, journal writes, x-action execution, x-feedback delivery, provider calls, campaign mutation, voucher mutation, wallet movement, public API behavior, or unsafe payload exposure changed.

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

The dashboard activity feed should now read as a general operating evidence stream rather than a settlement-only event list.
