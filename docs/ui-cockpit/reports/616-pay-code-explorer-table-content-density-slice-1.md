# Pay Code Explorer Table Content Density — Slice 1

Date: 2026-07-21

## Scope

This slice improves desktop result scanning by grouping related row facts into compact identity and lifecycle columns.

## Implemented

- Grouped Pay Code and template facts into one identity column.
- Grouped created and expiry facts into one lifecycle dates column.
- Reduced the desktop table from seven columns to five without removing sanitized read-model facts.
- Kept amount, status, and read-only row actions as dedicated scan targets.
- Set an explicit minimum table width so the compact groups remain readable inside horizontal overflow.

## Boundary

Presentation-only table content density. This does not change routes, controllers, backend queries, read-model hydration, filter semantics, pagination semantics, campaign context propagation, row action destinations, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, campaign mutation, provider calls, wallet behavior, Treasury behavior, persistence, public APIs, artifact generation, or money movement.

## Verification

- Focused frontend hydration coverage asserts the five-column desktop structure and grouped facts.
- Architecture coverage guards this wave's report and package-owned table markers.

## Result

Slice 1 ready for package-level verification and commit.
