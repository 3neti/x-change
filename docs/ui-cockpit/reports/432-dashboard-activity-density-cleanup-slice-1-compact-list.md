# Dashboard Activity Density Cleanup Slice 1 — Compact Activity List

Date: 2026-07-16

## Result

Reduced `/x/cockpit` activity density without changing read-model semantics.

## What changed

- Operator Issuance Activity now displays the latest five activity cards on the dashboard.
- Added a density summary explaining how many activities are visible.
- Added overflow guidance when additional activities exist.
- Moved campaign attribution, journal handoff, action handoff, and feedback handoff diagnostics behind native details sections.

## Boundary

This slice is UI-density presentation only.

It does not:

- change durable activity storage;
- change activity filters;
- change lifecycle truth;
- write journal entries;
- execute x-action actions;
- send x-feedback deliveries;
- call providers;
- mutate campaigns;
- mutate vouchers;
- move wallet funds;
- change public API behavior;
- change execution behavior;
- expose unsafe payloads.

## Verification

Command:

```bash
npm run test:frontend -- CockpitDashboardHydration.test.ts
```

Result:

```text
1 passed
24 passed
```

## Next checkpoint

Dashboard Activity Density Cleanup Slice 2 — Host publish / closure.
