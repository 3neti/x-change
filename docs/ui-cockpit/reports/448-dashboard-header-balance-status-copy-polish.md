# Dashboard Header Balance Status Copy Polish

Date: 2026-07-17

## Scope

Polish the `/x/cockpit` global header balance status placeholders so disconnected balance summaries read as operator-facing status facts.

## Changes

- `Summary not connected` is now `Internal balance not connected`.
- `Provider not connected` is now `Provider balance not connected`.
- Added frontend coverage for the default header HUD labels.

## Boundary

Presentation-only.

This slice did not connect wallet read models, call bank/provider services, reserve funds, move money, mutate vouchers, change lifecycle truth, write journal entries, execute x-action continuations, send x-feedback deliveries, dispatch campaigns, change public APIs, or expose unsafe payloads.

## Verification

```bash
npm run test:frontend -- CockpitLayout.test.ts CockpitDashboardShell.test.ts CockpitDashboardHydration.test.ts CockpitReadOnlyScenarioValidation.test.ts
```

Result: 4 files passed, 38 tests passed.
