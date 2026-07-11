# Cockpit Wave 32B — Voucher Detail Evidence Summary Read Model Contract

## Mission

Add a typed read-model contract for operator-safe Voucher Detail evidence summaries before provider hydration or UI adoption.

## Added Contract

`CockpitVoucherEvidenceSummaryData` carries:

- `key`;
- `label`;
- `status`;
- `description`;
- `read_only`;
- `available`;
- `source`.

`CockpitVoucherReadModelData` now optionally carries:

- `evidence_summary`.

## Boundary

The evidence summary contract is descriptive only. It does not fetch evidence, approve claims, execute vouchers, call providers, write journal entries, execute x-action, send x-feedback, mutate campaigns, or move money.

## Expected UI Result

No visible UI change is expected until the provider hydrates evidence summary records and the Vue page adopts them.

## Next Slice

Cockpit Wave 32C — Voucher Detail Evidence Summary Provider Hydration.
