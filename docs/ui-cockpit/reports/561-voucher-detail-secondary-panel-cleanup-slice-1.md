# Voucher Detail Secondary Panel Cleanup — Slice 1

Date: 2026-07-19

## Scope

This slice makes the Voucher Detail audit/follow-up secondary panel scan-friendly.

## Changes

- Converted the audit/follow-up panel into a compact disclosure.
- Renamed the panel eyebrow to `Follow-up status`.
- Renamed the heading to `Audit and follow-up details`.
- Added a compact density summary for:
  - evidence facts;
  - connected evidence facts;
  - disabled follow-ups.
- Kept journal evidence facts and disabled follow-up guidance inside the disclosure.
- Reworded follow-up action copy from read-only actions to disabled follow-up guidance.

## Verification

- `npm run test:frontend -- tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts`
- `vendor/bin/pest packages/x-change/tests/Unit/Architecture/VoucherDetailSecondaryPanelCleanupTest.php`
- `vendor/bin/pint --dirty --format agent`

## Boundary

This slice is presentation-only. It did not change routes, controllers, queries, read-model hydration, campaign context propagation, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, provider calls, wallet behavior, Treasury behavior, public APIs, persistence, or money movement.

## Next Recommended Checkpoint

Voucher Detail Secondary Panel Cleanup Slice 2 — host publish, asset drift check, browser smoke, build, and closure.
