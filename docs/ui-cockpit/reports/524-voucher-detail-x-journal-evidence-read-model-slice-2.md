# Voucher Detail x-journal Evidence Read Model — Slice 2

Date: 2026-07-19

## Scope

Render connected x-journal voucher evidence cleanly in the Voucher Detail audit panel.

## Outcome

- Voucher Detail now reads real x-journal entry summaries from `payload.summary` when no top-level summary is present.
- Added frontend verification for real x-journal-shaped entries.
- Confirmed unsafe journal payload keys remain absent from rendered text.
- Published package-owned Cockpit assets into the host app and confirmed no published asset drift.

## Verification

- `npm run test:frontend -- tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts`
- `php artisan x-change:install --force --no-interaction`
- `php artisan x-change:doctor --assets --no-interaction`

## Boundary

This slice changed read-only presentation only. It did not add journal writes, x-action execution, x-feedback delivery, campaign mutation, campaign dispatch, voucher mutation, claim execution, driver execution, provider calls, wallet behavior, Treasury behavior, public API changes, persistence, or money movement.

## Manual UI Expectation

When a Pay Code has x-journal voucher evidence, `/x/cockpit/pay-codes/{code}` should show the event label and the human-safe `payload.summary` in the Audit panel. Raw payloads, provider payloads, wallet data, and secrets should not appear.

## Next

Voucher Detail x-journal Evidence Read Model Slice 3 — focused backend/frontend/build closure and compass update.
