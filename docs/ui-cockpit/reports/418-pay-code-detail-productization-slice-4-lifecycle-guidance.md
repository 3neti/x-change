# Pay Code Detail Productization Slice 4 — Lifecycle Guidance

Date: 2026-07-16

## Scope

Add operator-readable lifecycle guidance to the primary Voucher Detail summary.

## Completed

- Added a `Lifecycle guidance` panel.
- Guidance is derived from sanitized display status only.
- Supported presentation states include:
  - available / ready
  - expired warning
  - claimed / redeemed
  - approval / review
- The panel explicitly states that Cockpit does not enforce lifecycle policy from this page.

## Verification

Command executed:

```bash
cd packages/x-change && npm run test:frontend -- CockpitVoucherDetailHydration.test.ts
```

Result:

- `19 passed`

## Boundary

This is display guidance only.

It does not:

- authorize distribution
- block distribution
- mutate vouchers
- execute drivers
- write journal entries
- execute actions
- send feedback
- call providers
- move wallet funds
- change claim UX behavior
- change public API behavior

## Next Slice

Pay Code Detail Productization Slice 5 — Host Publish / Drift Verification.
