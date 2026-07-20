# Pay Code Explorer Row Action Noise Reduction — Slice 1

Date: 2026-07-20

## Scope

This slice reduces repeated unavailable-action noise in Pay Code Explorer rows while preserving disabled action facts for inspection.

## Implemented

- Replaced the visible desktop `N unavailable` row action label with a quieter `More` disclosure.
- Moved unavailable action counts into screen-reader disclosure text.
- Added the same `More` disclosure treatment to mobile result cards.
- Kept disabled action labels and reasons available inside each disclosure.
- Preserved existing read-only `View details` and `Distribution` links.

## Boundary

Presentation-only row action noise reduction. This slice does not change routes, controllers, backend queries, read-model hydration, campaign context propagation, row action destinations, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, campaign mutation, provider calls, wallet behavior, Treasury behavior, public APIs, persistence, artifact generation, or money movement.

## Verification

- Focused Pay Code Explorer frontend hydration coverage.
- Architecture documentation guard for package source.

## Next Checkpoint

Pay Code Explorer Row Action Noise Reduction Slice 2 — publish host assets, verify asset drift, run build/browser checks, and close the wave.
