# Pay Code Explorer Row Action Width Polish — Slice 1

Date: 2026-07-20

## Scope

This slice stabilizes Pay Code Explorer row action controls so enabled links and unavailable summaries scan consistently across rows.

## Implemented

- Gave the desktop action column a fixed scan width.
- Rendered desktop enabled row action links as centered, stable-height pills.
- Rendered desktop unavailable summaries as centered, stable-height pills.
- Rendered mobile enabled row action links and unavailable summaries as centered, stable-height controls.
- Preserved existing read-only detail and distribution links.

## Boundary

Presentation-only row action width polish. This slice does not change routes, controllers, backend queries, read-model hydration, campaign context propagation, row action destinations, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, campaign mutation, provider calls, wallet behavior, Treasury behavior, public APIs, persistence, artifact generation, or money movement.

## Verification

- Focused Pay Code Explorer frontend hydration coverage.
- Architecture documentation guard for package source.

## Next Checkpoint

Pay Code Explorer Row Action Width Polish Slice 2 — publish host assets, verify asset drift, run browser/build checks, and close the wave.
