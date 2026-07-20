# Pay Code Explorer Compact Operations Table — Slice 2

Date: 2026-07-20

## Scope

This slice compacts the Pay Code Explorer desktop result rows and replaces wide text action buttons with icon-first desktop row actions.

## Implemented

- Converted desktop enabled row actions to compact 32px icon buttons.
- Added accessible `aria-label`, `title`, and screen-reader labels for row actions.
- Kept mobile action buttons text-first for readability.
- Replaced the desktop `More` text disclosure with a compact `MoreHorizontal` icon button.
- Reduced desktop table header and row cell padding.
- Kept Owner, Last Activity, and disabled action labels behind the existing row disclosure.

## Boundary

Presentation-only table compactness slice. This does not change routes, controllers, backend queries, read-model hydration, filter semantics, pagination semantics, campaign context propagation, row action destinations, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, campaign mutation, provider calls, wallet behavior, Treasury behavior, persistence, public APIs, artifact generation, or money movement.

## Verification

- Focused frontend hydration coverage updated for icon action accessibility, compact action width, mobile text preservation, and shorter row padding.
- Architecture guard updated for icon-first row actions.

## Result

Slice 2 ready for package-level verification and commit.
