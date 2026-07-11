# Cockpit Wave 32D — Voucher Detail Evidence Summary UI Presentation

## Mission

Render the hydrated Voucher Detail evidence summary in the Cockpit UI without adding mutation behavior.

## Implemented

The Voucher Detail Evidence panel now renders hydrated evidence summary items when `voucher.evidence_summary` is present.

Visible evidence cards include:

- lifecycle facts;
- claim evidence;
- approval evidence;
- execution evidence;
- journal evidence;
- action evidence;
- feedback evidence.

Each card remains read-only and may show the sanitized source of the read model fact.

## Boundary

This slice does not approve claims, execute vouchers, call providers, write journal entries, execute actions, send feedback, mutate campaigns, or expose raw payloads.

## Expected UI Result

On `/x/cockpit/pay-codes/{code}`, operators should see an Evidence panel headed `Evidence summary` with read-only evidence cards when the read model provides `voucher.evidence_summary`.

## Verification

Frontend tests cover the hydrated evidence summary UI and the default placeholder fallback.

Published host Cockpit assets were refreshed and verified clean with the asset doctor.

## Next Slice

Cockpit Wave 32E — Voucher Detail Evidence Browser / Publish Verification.
