# Dashboard Lower-Panel Simplification Slice 1

Date: 2026-07-19

## Scope

Reduce visual density inside the dashboard Funding Status panel without changing funding read models, balance semantics, wallet behavior, or Treasury behavior.

## Outcome

- Added a compact funding density summary for funding facts, semantic categories, and money-movement status.
- Moved the funding semantics explainer cards behind a disclosure.
- Preserved all funding metrics, bridge-estimate language, and read-only money-movement boundaries.

## Verification

- `npm run test:frontend -- tests/frontend/cockpit/CockpitDashboardFoundation.test.ts`

## Boundary

This slice did not add routes, controllers, balance computation changes, wallet behavior, Treasury behavior, voucher mutation, claim execution, driver execution, journal writes, action execution, feedback sends, provider calls, campaign mutation, public API changes, persistence, or money movement.

## Next

Dashboard Lower-Panel Simplification Slice 2 — compact Claim Status and Review Queue panels.
