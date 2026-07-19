# Pay Code Explorer Campaign Context Visual Polish — Slice 1

Date: 2026-07-19

## Scope

This slice improves campaign-context readability on `/x/cockpit/pay-codes` when the Explorer is opened from a campaign-aware route.

## Implemented

- Renamed the panel from `Campaign Explorer Context` to `Campaign Context`.
- Replaced raw mutation reason display with operator-facing copy.
- Added a primary four-field campaign context summary:
  - Planning Key
  - Campaign ID
  - Recipient ID
  - Source
- Moved the full campaign context identifier set into `Campaign filter details`.
- Preserved hidden GET fields, campaign return link, row detail links, and distribution links.

## Boundary

This is presentation-only. It does not change routes, controllers, queries, read-model hydration, campaign context propagation, row action links, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, campaign mutation, provider calls, wallet behavior, Treasury behavior, public APIs, persistence, or money movement.

## Verification

- Focused campaign Explorer frontend navigation coverage.
- Focused Pay Code Explorer frontend hydration coverage.
- Architecture documentation guard.

## Next Checkpoint

Pay Code Explorer Campaign Context Visual Polish Slice 2 — host publish, asset drift, browser smoke, build, and closure.
