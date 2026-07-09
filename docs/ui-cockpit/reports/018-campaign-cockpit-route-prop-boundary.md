# Host Integration Slice 1D — Campaign Cockpit Read Model Route Prop Boundary

## Scope

Expose the existing campaign Cockpit adoption read model through the x-change Cockpit dashboard as a read-only Inertia prop.

## Route Prop

The dashboard route now includes:

```text
campaign_read_model
```

This is a read-only Inertia prop sourced from `CockpitReadModelProviderContract::forCampaignAdoption()`.

## Optional Query Context

The dashboard route accepts optional read context:

```text
campaign_planning_key
campaign_execution_id
```

These values are normalized as strings and passed to the optional campaign adapter seam. Missing context degrades safely to an unavailable campaign read model.

## Boundaries

- No campaign mutation endpoints
- No Pay Code generation through campaign
- No delivery dispatch
- No journal writes
- No feedback sends or retries
- No wallet reads or writes
- No money movement

## Notes

The route prop boundary does not introduce a hard dependency on x-campaign. If the configured campaign Cockpit workspace is unavailable, x-change still renders the dashboard with a safe unavailable `campaign_read_model`.
