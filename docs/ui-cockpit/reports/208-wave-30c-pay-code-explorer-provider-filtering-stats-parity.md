# Cockpit Wave 30C — Pay Code Explorer Provider Filtering and Stats Parity

## Status

Complete.

## Implemented

`VoucherLifecycleCockpitReadModelProvider::forPayCodeList()` now applies legacy-compatible read-only filtering:

- `payCodeSearch`
- `payCodeStatus`
- legacy status inference
- sanitized read-model stats
- read-only status filter options

Search parity includes the legacy index searchable fields internally:

- code
- mobile
- account number
- bank code
- status
- display status
- formatted amount
- amount
- template

The returned read model still excludes unsafe fields from rendered records.

## Boundary

This slice does not change `/x/pay-codes`, voucher lifecycle behavior, provider behavior, wallet behavior, journal/action/feedback behavior, or campaign behavior.

Filtering is read-model-only.

## Next slice

```text
Cockpit Wave 30D — Pay Code Explorer Controller Query Intake
```
