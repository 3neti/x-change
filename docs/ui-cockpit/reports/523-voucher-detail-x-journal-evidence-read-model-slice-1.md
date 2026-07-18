# Voucher Detail x-journal Evidence Read Model — Slice 1

Date: 2026-07-19

## Scope

Start a connected-service read model wave by verifying Voucher Detail can hydrate real x-journal entries as read-only evidence for a Pay Code.

## Outcome

- Added route-level acceptance for `/x/cockpit/pay-codes/{code}` with a real voucher and a real `execution_journal_entries` row.
- Verified Voucher Detail receives `read_model.journal.status = available`.
- Verified the journal read model remains evidence-only and does not write journal entries from Cockpit.
- Hardened the default Cockpit redactor so nested `wallet` keys are redacted before read-model payloads reach the UI.
- Confirmed x-journal payload `raw_payload`, `provider_payload`, and `wallet` data remain redacted.

## Verification

- `vendor/bin/pest tests/Unit/Cockpit/DefaultCockpitRedactorTest.php tests/Feature/Cockpit/CockpitVoucherDetailXJournalEvidenceReadModelTest.php`

## Boundary

This slice did not add journal writes, x-action execution, x-feedback delivery, campaign mutation, campaign dispatch, voucher mutation, claim execution, driver execution, provider calls, wallet behavior, Treasury behavior, public API changes, persistence, or money movement.

## Next

Voucher Detail x-journal Evidence Read Model Slice 2 — render/verify Voucher Detail audit/evidence panels consume the connected x-journal entries cleanly, then publish assets and close the wave.
