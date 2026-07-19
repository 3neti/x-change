# Pay Code Explorer Row / Mobile Density Polish — Slice 1

Date: 2026-07-19

## Scope

This slice improves `/x/cockpit/pay-codes` row readability on small screens.

## Implemented

- Added mobile-first Pay Code result cards below the results header.
- Kept the existing desktop results table for medium and larger screens.
- Hid the wide table below `md` to avoid horizontal table pressure on phones.
- Preserved enabled row links to Voucher Detail and Distribution Workspace.
- Preserved disabled-action summaries without adding any executable actions.

## Boundary

This is presentation-only. It does not change routes, controllers, queries, read-model hydration, campaign context propagation, row action links, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, provider calls, wallet behavior, Treasury behavior, public APIs, persistence, or money movement.

## Verification

- Focused Pay Code Explorer frontend hydration coverage.
- Architecture documentation guard.

## Next Checkpoint

Pay Code Explorer Row / Mobile Density Polish Slice 2 — host publish, asset drift, browser smoke, build, and closure.
