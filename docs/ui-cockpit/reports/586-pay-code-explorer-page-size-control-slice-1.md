# Pay Code Explorer Page Size Control — Slice 1

Date: 2026-07-20

## Scope

This slice adds a client-side rows-per-page control to `/x/cockpit/pay-codes`.

## Implemented

- Added `10`, `25`, and `50` rows-per-page options.
- Kept `25` as the default result density.
- Reset pagination to page 1 when the operator changes row density.
- Preserved full sanitized read-model totals, link counts, disabled-action counts, search filters, status filters, detail links, and distribution links.
- Kept pagination browser-local and read-only.

## Boundary

Presentation-only client-side density control. This slice does not change routes, controllers, backend queries, read-model hydration, campaign context propagation, row action links, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, campaign mutation, provider calls, wallet behavior, Treasury behavior, public APIs, persistence, artifact generation, or money movement.

## Verification

- Focused Pay Code Explorer frontend hydration coverage.
- Architecture documentation guard for package source.

## Next Checkpoint

Pay Code Explorer Page Size Control Slice 2 — publish host assets, verify asset drift, run browser/build checks, and close the wave.
