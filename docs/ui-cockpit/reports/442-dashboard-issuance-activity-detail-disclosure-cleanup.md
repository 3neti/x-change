# Dashboard Issuance Activity Detail Disclosure Cleanup

Date: 2026-07-16

## Scope

Reduce visual noise in the `/x/cockpit` Issuance Activity cards by hiding empty disconnected handoff detail disclosures.

## Change

- Preserved the top-level Journal, Action, and Feedback status badges.
- Kept detail disclosures visible when meaningful connected evidence exists:
  - journal entry, reference number, event type, diagnostic, or confirmed journal write;
  - action hint, action run, suggested action, or confirmed action execution;
  - feedback intent, delivery plan, receipt, channel, planned deliveries, or confirmed feedback send.
- Hid detail disclosures when the only handoff data is disconnected source/reason metadata.

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

Disconnected activity cards continue showing:

- `Journal: Not connected`
- `Action: Not connected`
- `Feedback: Not connected`

But they no longer show empty:

- `Journal status details`
- `Action status details`
- `Feedback status details`

Connected or planned handoff activity still shows those details.
