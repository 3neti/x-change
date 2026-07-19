# Voucher Detail Page Polish — Slice 2

Date: 2026-07-19

## Scope

Polished Voucher Detail connected-service copy for operator use.

Changed the page language from engineering-oriented labels toward:

- `Connected services`
- `Audit Trail`
- `Follow-Up Actions`
- `Notifications`
- `Audit and follow-up guidance`
- `Notification status`

Raw package states remain accepted by the component, but the primary operator display translates common states such as `not_wired`, `not-loaded`, and `read-model-ready`.

## Boundary

Presentation-copy update only.

No route behavior, read-model hydration, distribution dispatch, feedback delivery, x-action execution, journal write, voucher mutation, provider call, persistence, wallet behavior, Treasury behavior, public API behavior, or money movement changed.

## Verification

- `npm run test:frontend -- tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts`
  - Result: 1 file passed, 21 tests passed.
