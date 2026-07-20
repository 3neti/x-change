# Pay Code Explorer Top Control Strip Simplification — Slice 1

Date: 2026-07-20

## Scope

This slice simplifies the Pay Code Explorer top utility area by grouping secondary diagnostic panels behind one disclosure.

## Implemented

- Added a single `Page details` disclosure below the primary list summary and campaign context.
- Moved row action guidance, list totals, connected service badges, and connected service readiness under that disclosure.
- Kept all secondary facts and existing `data-testid` contracts available for tests and inspection.
- Preserved primary `Quick Generate`, `Clear filters`, Search, pagination, detail links, and distribution links.

## Boundary

Presentation-only top control strip simplification. This slice does not change routes, controllers, backend queries, read-model hydration, campaign context propagation, row action destinations, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, campaign mutation, provider calls, wallet behavior, Treasury behavior, public APIs, persistence, artifact generation, or money movement.

## Verification

- Focused Pay Code Explorer frontend hydration coverage.
- Architecture documentation guard for package source.

## Next Checkpoint

Pay Code Explorer Top Control Strip Simplification Slice 2 — publish host assets, verify asset drift, run build/browser checks, and close the wave.
