# Cockpit Wave 31B — Pay Code Explorer Row Action Read Model Contract

## Mission

Add an explicit read-only row action contract for Pay Code Explorer records before wiring provider hydration or UI rendering.

## Added Contract

`CockpitPayCodeRowActionData` carries:

- `key`;
- `label`;
- `enabled`;
- `read_only`;
- `href`;
- `reason`.

`CockpitPayCodeListRecordData` now optionally carries:

- `actions`.

## Boundary

The row action contract is descriptive only. It does not execute a voucher, approve a claim, call a provider, write journal entries, execute x-action, send x-feedback, mutate campaign state, or move money.

## Expected UI Result

No visible UI change is expected until the provider hydrates row actions and the Vue table adopts the per-row action payload.

## Next Slice

Cockpit Wave 31C — Pay Code Explorer Provider Row Action Hydration.
