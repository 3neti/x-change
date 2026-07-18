# Campaign Selected Context UI Productization — Slice 1

Date: 2026-07-18

## Result

Pass.

The dashboard campaign panel now presents selected campaign context as an operator-facing prefill surface instead of a mostly diagnostic campaign availability card.

## What Changed

- Added a selected campaign context card to `/x/cockpit`.
- Shows scan-friendly read-only facts:
  - planning key,
  - execution id,
  - template,
  - amount,
  - recipient,
  - purpose.
- Renamed the primary campaign-to-generation CTA to `Generate from this campaign`.
- Kept campaign details behind the existing disclosure control.
- Kept campaign context explicitly `Prefill Only`.

## Boundary Confirmation

This slice changes presentation only. It does not add routes, controllers, campaign mutations, campaign dispatch, Pay Code generation through campaign, feedback sends, journal writes, action execution, provider calls, wallet behavior, Treasury behavior, public API changes, durable persistence, or money movement.

## Verification

From `packages/x-change`:

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitDashboardHydration.test.ts
```

Result: 1 file passed, 31 tests passed.

From the host root:

```bash
vendor/bin/pint --dirty --format agent
```

Result: passed.

## Next Slice

Campaign Selected Context UI Productization Slice 2 — publish package UI to the host, run asset drift verification, run focused frontend verification, run host build, and close the wave.
