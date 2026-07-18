# Voucher Detail / Distribution Layout Polish — Slice 2 — Distribution Workspace

Date: 2026-07-18

## Scope

Polish the `/x/cockpit/pay-codes/{code}/distribution` top summary so manual distribution work is easier to scan.

## Changes

- Renamed the primary action block from `Primary next step` to `Manual next step`.
- Reflowed the manual next-step card and manual distribution checklist into a responsive two-column layout on wide screens.
- Kept channel and artifact readiness as a separate scan row below the manual work area.
- Preserved the existing read-only actions:
  - open claim URL;
  - copy claim URL;
  - return to Pay Code Detail;
  - return to Pay Codes.

## Boundary

This is a presentation-only layout change. It does not change route behavior, read-model hydration, claim behavior, distribution dispatch, feedback delivery, campaign mutation, voucher lifecycle mutation, driver execution, artifact generation, journal writes, provider calls, wallet behavior, Treasury behavior, public API behavior, or money movement.

## Verification

- `npm run test:frontend -- CockpitDistributionWorkspaceFoundation.test.ts`
  - Result: 1 file passed, 14 tests passed.
