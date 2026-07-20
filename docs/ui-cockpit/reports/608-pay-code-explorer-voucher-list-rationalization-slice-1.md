# Pay Code Explorer Voucher List Rationalization — Slice 1

Date: 2026-07-20

## Scope

This slice reframes `/x/cockpit/pay-codes` around the voucher list workflow: status summary, search/filter, and results.

## Implemented

- Moved Quick Generate, Clear filters, and Read-only into the compact page header.
- Replaced the old operator summary with voucher lifecycle summary cards: Total, Active, Redeemed, Expired, and Needs Attention.
- Moved current-search, read-model status, record count, and payload policy into Page details.
- Updated search copy to match the `/vouchers` mental model.
- Reordered the result table to `Pay Code`, `Amount`, `Type / Template`, `Status`, `Created`, `Expires`, and `Actions`.
- Tucked Owner and Last Activity into row disclosures.

## Boundary

Presentation-only Explorer rationalization. This slice does not change routes, controllers, backend queries, read-model hydration, filter semantics, campaign context propagation, row action destinations, pagination semantics, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, campaign mutation, provider calls, wallet behavior, Treasury behavior, public APIs, persistence, artifact generation, or money movement.

## Verification

- Focused Pay Code Explorer frontend hydration and foundation coverage.
- Architecture documentation guard for package source.

## Next Checkpoint

Pay Code Explorer Voucher List Rationalization Slice 2 — publish host assets, verify asset drift, run build/browser checks, and close the wave.
