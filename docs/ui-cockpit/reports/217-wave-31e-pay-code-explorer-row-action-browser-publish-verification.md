# Cockpit Wave 31E — Pay Code Explorer Row Action Browser / Publish Verification

## Mission

Verify the Wave 31 row-action UI through published host assets and browser smoke coverage.

## Verification Scope

The browser smoke verifies:

- `/x/cockpit/pay-codes` renders with rows;
- enabled `View details` row-action links point to `/x/cockpit/pay-codes/{code}`;
- enabled `Distribution` row-action links point to `/x/cockpit/pay-codes/{code}/distribution`;
- disabled future `Notify recipient` actions remain visible but non-executable;
- clicking `View details` navigates to the read-only Cockpit voucher detail page;
- unsafe payload markers are not rendered.

The asset doctor verifies published Cockpit host assets match package source.

## Boundary

The browser smoke performs GET navigation only. It does not submit claim approvals, execute vouchers, call providers, mutate wallets, write journal entries, execute x-action, send x-feedback, mutate campaign state, or move money.

## Expected UI Result

Operators can use row-level links from `/x/cockpit/pay-codes` to inspect read-only Cockpit detail and distribution surfaces.

## Next Slice

Cockpit Wave 31F — Pay Code Explorer Row Action Runtime Parity Closure.
