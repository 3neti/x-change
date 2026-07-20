# Pay Code Explorer Compact Operations Table — Slice 1

Date: 2026-07-20

## Scope

This slice compacts the visible Pay Code Explorer operating area above the result table.

## Implemented

- Replaced the verbose lifecycle summary card with slim status pills.
- Moved the Pay Code search form directly into the primary operations band.
- Removed explanatory helper copy from the visible status pills.
- Kept current search, read-model facts, payload policy, and mutation-boundary language behind Page details.
- Tightened search input, status select, Search, and Clear controls from 40px to 36px height.

## Boundary

Presentation-only Pay Code Explorer compactness slice. This does not change routes, controllers, backend queries, read-model hydration, filter semantics, pagination semantics, campaign context propagation, row action destinations, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, campaign mutation, provider calls, wallet behavior, Treasury behavior, persistence, public APIs, artifact generation, or money movement.

## Verification

- Focused frontend hydration and foundation coverage updated for the compact operations band.
- Architecture guard added for the compact operations table wave.

## Result

Slice 1 ready for package-level verification and commit.
