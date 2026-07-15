# Execution Integration Slice 14 — Cockpit Durable Summary Projection UI Surfacing

## Status

Completed on 2026-07-15.

## Mission

Surface durable execution handoff summary projection status in Cockpit Recent Activity so operators can see when action and feedback handoff statuses are backed by the persisted `execution.handoff.summary.recorded` journal event.

## Implementation

Added optional display fields to dashboard activity items:

- `projection_badge`
- `projection_status`
- `projection_detail`
- `projection_targets`

For execution activity rows backed by a post-pipeline summary journal event, Cockpit now reports:

```text
projection_badge: Durable summary evidence
projection_status: durable_summary_evidence_available
projection_detail: Action and feedback statuses are projected from x-journal execution.handoff.summary.recorded.
```

The Vue Recent Activity panel renders this as a small read-only evidence block only when the projection fields are present.

## Boundary

This is a presentation/read-model surfacing slice only.

It does not:

- execute actions
- send feedback
- write journal entries
- call providers
- mutate vouchers
- access wallets
- move money
- make Cockpit an execution surface

## Files Changed

- `src/Data/Cockpit/CockpitDashboardActivityData.php`
- `src/Services/Cockpit/OptionalCockpitIntegrationReadModels.php`
- `resources/js/cockpit/types.ts`
- `resources/js/cockpit/pages/Dashboard.vue`
- `resources/js/cockpit/components/CockpitRecentActivityPanel.vue`
- `tests/Feature/Cockpit/CockpitExecutionActivityProjectionTest.php`

## Verification

Added a failing test first for the visible projection fields on durable-summary execution activity.

The test now proves Cockpit dashboard read models expose operator-visible durable summary projection fields when `execution.handoff.summary.recorded` exists.

## Next Recommended Slice

Execution Integration Slice 15 — Cockpit published asset sync and browser verification for durable summary projection UI.

That slice should publish package assets into the host app, run the drift guard, and verify the Recent Activity evidence block in browser-facing output.
