# Pay Code Explorer Result Metric Width Stability

Date: 2026-07-20

## Scope

This slice stabilizes the Results metric cards in `/x/cockpit/pay-codes` so `Showing`, `Total Rows`, `Links`, and `Disabled` do not visually resize while operators move between result pages.

## Implemented

- Set a stable desktop summary width on the metric grid.
- Preserved the two-column mobile and four-column desktop layout.
- Added `min-w-0` on metric cards.
- Rendered metric values with `font-mono`, `tabular-nums`, and `whitespace-nowrap`.
- Preserved pagination behavior, result counts, row links, search/status filters, and read-only copy.

## Boundary

Presentation-only width stabilization. This slice does not change routes, controllers, backend queries, read-model hydration, campaign context propagation, row action links, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, campaign mutation, provider calls, wallet behavior, Treasury behavior, public APIs, persistence, artifact generation, or money movement.

## Verification

- Focused Pay Code Explorer frontend hydration coverage.
- Architecture documentation guard.
- Host asset publish and drift verification.
- Host frontend build.

## Next Checkpoint

Manual browser inspection of `/x/cockpit/pay-codes`, then continue page-focused Cockpit polish or pick the next real integration wiring wave.
