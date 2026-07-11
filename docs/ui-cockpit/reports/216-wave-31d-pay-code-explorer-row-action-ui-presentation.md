# Cockpit Wave 31D — Pay Code Explorer Row Action UI Presentation

## Mission

Render provider-hydrated Pay Code Explorer row actions in the Cockpit results table.

## UI Change

Rows can now render:

- enabled read-only `View details` links;
- enabled read-only `Distribution` links;
- disabled future actions such as `Notify recipient`.

The table falls back to the old disabled baseline actions when hydrated records do not provide per-row actions.

## Boundary

Enabled links are GET navigation only. Disabled actions remain disabled buttons. This slice does not add mutation routes, voucher execution, provider calls, wallet mutation, journal writes, x-action execution, x-feedback delivery, campaign mutation, or unsafe payload display.

## Expected UI Result

`/x/cockpit/pay-codes` rows should show clickable `View details` and `Distribution` links when the read model provides row actions.

## Next Slice

Cockpit Wave 31E — Pay Code Explorer Row Action Browser / Publish Verification.
