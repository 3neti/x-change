# Cockpit Wave 31C — Pay Code Explorer Provider Row Action Hydration

## Mission

Hydrate Pay Code Explorer list records with explicit read-only row actions.

## Runtime Change

`VoucherLifecycleCockpitReadModelProvider` now attaches four row actions to each sanitized Pay Code Explorer record:

- `detail`, enabled, read-only, links to `/x/cockpit/pay-codes/{code}`;
- `distribution`, enabled, read-only, links to `/x/cockpit/pay-codes/{code}/distribution`;
- `timeline`, disabled, read-only, blocked pending journal visibility/redaction wiring;
- `notify`, disabled, read-only, blocked pending x-feedback delivery authorization.

Pay Code values are URL-encoded before being embedded in action hrefs.

## Boundary

The provider only creates operator-safe navigation metadata. It does not execute the actions, mutate vouchers, call providers, write journal entries, execute x-action, send x-feedback, mutate campaigns, or move money.

## Expected UI Result

No visible UI change until the results table adopts per-row actions.

## Next Slice

Cockpit Wave 31D — Pay Code Explorer Row Action UI Presentation.
