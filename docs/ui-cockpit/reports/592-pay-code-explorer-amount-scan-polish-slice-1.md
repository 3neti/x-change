# Pay Code Explorer Amount Scan Polish — Slice 1

Date: 2026-07-20

## Scope

This slice makes Pay Code Explorer monetary values easier to scan without changing amount read-model facts.

## Implemented

- Right-aligned the desktop `Amount` column header and values.
- Rendered desktop amount values with `font-mono` and `tabular-nums`.
- Rendered mobile amount card values with `font-mono` and `tabular-nums`.
- Preserved the formatted amount string supplied by the sanitized read model.

## Boundary

Presentation-only amount scan polish. This slice does not change routes, controllers, backend queries, read-model hydration, amount calculation, pricing, funding, campaign context propagation, row action links, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, campaign mutation, provider calls, wallet behavior, Treasury behavior, public APIs, persistence, artifact generation, or money movement.

## Verification

- Focused Pay Code Explorer frontend hydration coverage.
- Architecture documentation guard for package source.

## Next Checkpoint

Pay Code Explorer Amount Scan Polish Slice 2 — publish host assets, verify asset drift, run browser/build checks, and close the wave.
