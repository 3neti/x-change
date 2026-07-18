# Voucher Detail x-journal Evidence Read Model — Slice 3 Closure

Date: 2026-07-19

## Scope

Close the Voucher Detail x-journal Evidence Read Model wave with focused backend, frontend, asset drift, and host build verification.

## Outcome

- Voucher Detail can hydrate real voucher-scoped x-journal entries as read-only evidence.
- Connected x-journal entries render their safe `payload.summary` in the Audit panel.
- Cockpit redaction now redacts nested `wallet` keys before connected-service payloads reach the UI.
- Published Cockpit assets match package source.
- Host production build passes.

## Verification

- `vendor/bin/pest tests/Unit/Cockpit/DefaultCockpitRedactorTest.php tests/Feature/Cockpit/CockpitVoucherDetailXJournalEvidenceReadModelTest.php tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php`
- `npm run test:frontend -- tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts`
- `php artisan x-change:doctor --assets --no-interaction`
- `npm run build`

Build note: Vite completed successfully with the existing third-party Rolldown `/* #__PURE__ */` annotation warning from `reka-ui` / `@vueuse/core`.

## Boundary

No journal writes, x-action execution, x-feedback delivery, campaign mutation, campaign dispatch, voucher mutation, claim execution, driver execution, provider calls, wallet behavior, Treasury behavior, public API changes, persistence, or money movement were added.

## Manual UI Expectation

On `/x/cockpit/pay-codes/{code}`, a Pay Code with matching x-journal voucher entries should show journal event evidence in the Audit panel using operator-safe summary text. Wallet/provider/raw payload details must not appear.

## Next

Recommended next wave: connect the next voucher-scoped service read model, likely x-feedback delivery summaries or x-action follow-up CTAs, while keeping all actions disabled until explicitly approved.
