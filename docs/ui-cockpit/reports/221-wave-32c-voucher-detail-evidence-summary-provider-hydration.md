# Cockpit Wave 32C — Voucher Detail Evidence Summary Provider Hydration

## Mission

Hydrate the Voucher Detail evidence summary from the existing read-only lifecycle and integration read models.

## Implemented

`VoucherLifecycleCockpitReadModelProvider` now adds read-only evidence summary records for:

- lifecycle facts;
- claim evidence;
- approval evidence;
- execution evidence;
- journal evidence;
- action evidence;
- feedback evidence.

## Boundary

This slice only summarizes existing read-model state. It does not approve claims, execute vouchers, call providers, move money, write journal entries, execute actions, send feedback, mutate campaigns, or expose raw payloads.

## Expected UI Result

No visible UI change is expected until the Voucher Detail Vue page renders `voucher.evidence_summary`.

## Verification

Focused package tests cover provider hydration and the typed evidence summary contract.

## Next Slice

Cockpit Wave 32D — Voucher Detail Evidence Summary UI Presentation.
