# Cockpit Wave 31A — Pay Code Explorer Detail Navigation / Row Action Parity Audit

## Mission

Define the next read-only Pay Code Explorer parity slice after Wave 30 search/status filtering.

Wave 31 should make the Cockpit Pay Code Explorer rows behave like useful operator navigation rows without changing voucher lifecycle behavior.

## Current State

`/x/cockpit/pay-codes` now has:

- read-only `search` and `status` GET filters;
- sanitized list records;
- legacy-compatible status inference;
- filtered stats;
- active filter summary and clear filters link;
- Activity navigation context from Operator Issuance Activity cards.

The current row action baseline is still older scaffold copy:

- `View details` is disabled;
- `Open timeline` is disabled;
- `Notify recipient` is disabled.

This is now partially stale because Cockpit already has read-only routes for:

- `/x/cockpit/pay-codes/{code}`;
- `/x/cockpit/pay-codes/{code}/distribution`;

## Authorized Wave 31 Scope

Wave 31 may add read-only row action runtime parity for sanitized list rows:

1. row-level detail navigation to `/x/cockpit/pay-codes/{code}`;
2. row-level distribution navigation to `/x/cockpit/pay-codes/{code}/distribution`;
3. explicit disabled future actions for non-authorized behavior such as timeline, approve, resend, notify, execute, or provider activity;
4. browser smoke coverage for row actions;
5. published asset drift verification and closure records.

## Explicit Boundaries

Wave 31 must not add:

- voucher mutation;
- claim approval;
- redemption execution;
- execution-driver invocation;
- provider calls;
- wallet mutation;
- journal writes;
- x-action execution;
- x-feedback delivery;
- campaign mutation;
- raw provider payload display;
- raw recipient payload display;
- raw wallet/internal balance display.

## Planned Slices

| Slice | Purpose | Expected UI effect |
|---|---|---|
| 31A | Row action parity audit | No UI change |
| 31B | Row action read-model contract | No visible UI until adopted |
| 31C | Provider row action hydration | No visible UI until adopted |
| 31D | Row action UI presentation | Rows show enabled read-only detail/distribution links |
| 31E | Browser and publish verification | No new UI beyond 31D |
| 31F | Closure / next planning | No UI change |

## Next Slice

Cockpit Wave 31B — Pay Code Explorer Row Action Read Model Contract.
