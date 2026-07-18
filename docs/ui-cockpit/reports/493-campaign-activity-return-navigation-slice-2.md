# Campaign Activity Return Navigation Productization — Slice 2

Date: 2026-07-18

## Result

Pass.

Dashboard Issuance Activity now exposes campaign-attributed Pay Code activity with a visible campaign context strip and direct read-only return links.

## What Changed

- Added a compact `Campaign context` panel inside campaign-attributed activity cards.
- Shows scan-friendly facts:
  - planning key,
  - execution id,
  - recipient reference.
- Added direct visible links:
  - `Return to Campaign Dashboard`,
  - `Open campaign-filtered Explorer`.
- Preserved the existing collapsed campaign attribution detail disclosure.

## Boundary Confirmation

This slice is UI-only. It does not add routes, controllers, campaign mutation, campaign dispatch, Pay Code generation through campaign, feedback sends, journal writes, action execution, provider calls, wallet behavior, Treasury behavior, public API changes, persistence, or money movement.

## Verification

From the host root:

```bash
vendor/bin/pint --dirty --format agent
```

Result: passed.

From `packages/x-change`:

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitDashboardHydration.test.ts
```

Result: 1 file passed, 31 tests passed.

## Next Slice

Campaign Activity Return Navigation Productization Slice 3 — host publish / asset drift / frontend verification / build closure.
