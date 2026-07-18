# Dashboard Lower-Panel Simplification Slice 3

Date: 2026-07-19

## Scope

Make the dashboard Campaigns panel easier to scan in both selected-campaign and no-campaign contexts.

## Outcome

- Added a compact Campaigns summary for surfaces, panels, actions, and selected-campaign status.
- Preserved the existing campaign details disclosure for surfaces, panels, actions, and mutation boundary details.
- Preserved selected campaign prefill facts and Quick Generate links.

## Verification

- `npm run test:frontend -- tests/frontend/cockpit/CockpitDashboardHydration.test.ts`

## Boundary

This slice did not add routes, controllers, campaign mutation, campaign dispatch, Pay Code generation through campaign, feedback sends, journal writes, action execution, provider calls, wallet behavior, Treasury behavior, public API changes, persistence, or money movement.

## Next

Dashboard Lower-Panel Simplification Slice 4 — host publish, drift check, backend/frontend verification, build, and closure.
