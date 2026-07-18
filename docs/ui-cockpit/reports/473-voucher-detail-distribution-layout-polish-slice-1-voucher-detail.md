# Voucher Detail / Distribution Layout Polish — Slice 1 — Voucher Detail

Date: 2026-07-18

## Scope

Polish the `/x/cockpit/pay-codes/{code}` top operator summary so the Voucher Detail page is easier to scan before operators reach the deeper read-model panels.

## Changes

- Renamed the primary action block from `Primary next step` to `Operator next step`.
- Reflowed the operator next-step and lifecycle guidance panels into a responsive two-column layout on wide screens.
- Kept evidence readiness as a separate summary row below the action/lifecycle pair.
- Preserved the existing read-only actions:
  - open claim URL;
  - copy claim URL;
  - open distribution workspace;
  - return to Pay Codes.

## Boundary

This is a presentation-only layout change. It does not change route behavior, read-model hydration, claim behavior, distribution dispatch, feedback delivery, campaign mutation, voucher lifecycle mutation, driver execution, artifact generation, journal writes, provider calls, wallet behavior, Treasury behavior, public API behavior, or money movement.

## Verification

- `npm run test:frontend -- CockpitVoucherDetailFoundation.test.ts CockpitVoucherDetailHydration.test.ts`
  - Result: 2 files passed, 26 tests passed.
