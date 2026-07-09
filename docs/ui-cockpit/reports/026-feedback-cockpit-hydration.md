# Host Integration Slice 2C — Feedback Cockpit Hydration

## Status

Complete.

## Scope

Hydrate x-feedback delivery summaries into the existing Cockpit Voucher Detail distribution surface.

## Implemented

- Voucher Detail maps available `read_model.feedback.deliveries` entries into read-only distribution rows.
- Delivery status and channel are visible as communication facts.
- Feedback redaction policy is displayed in the row helper text.
- Static fallback channel rows remain when the x-feedback read model is unavailable or empty.

## Boundaries

This slice does not:

- send feedback
- retry delivery
- call providers
- expose recipient addresses
- expose provider payloads
- expose raw payloads
- write journal entries
- execute actions
- mutate vouchers
- move money

## Verification

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitVoucherDetailHydration.test.ts tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts
```

Result:

```text
2 passed, 12 tests
```
