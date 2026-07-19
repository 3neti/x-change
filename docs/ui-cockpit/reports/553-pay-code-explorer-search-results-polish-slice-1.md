# Pay Code Explorer Search / Results Polish — Slice 1

Date: 2026-07-19

## Scope

This slice improves `/x/cockpit/pay-codes` scan flow by making the current search state explicit and demoting duplicate diagnostic panels.

## Implemented

- Added a compact `Current Search` summary inside the primary Pay Code Explorer card.
- Shows search term, status filter, visible row count, and campaign-context presence as read-only facts.
- Moved row action guidance, list totals, connected-service badges, and connected-service readiness into disclosure panels.
- Replaced `navigation-only` with the friendlier `Links only` badge.

## Boundary

This is presentation-only. It does not change routes, controllers, queries, read-model hydration, campaign context propagation, row action links, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, provider calls, wallet behavior, Treasury behavior, public APIs, persistence, or money movement.

## Verification

- Focused Pay Code Explorer frontend hydration coverage.
- Architecture documentation guard.

## Next Checkpoint

Pay Code Explorer Search / Results Polish Slice 2 — host publish, asset drift check, browser smoke, build, and closure.
