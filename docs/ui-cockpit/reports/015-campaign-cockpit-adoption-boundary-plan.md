# Host Integration Slice 1A — Campaign Cockpit Adoption Boundary Plan

This slice starts:

```text
x-change Host Integration Slice 1 — Read-only Campaign Cockpit Adoption
```

The goal is to let the x-change Cockpit consume `x-campaign` readiness/adoption facts without enabling campaign execution or campaign mutation.

## Source Inputs

Use these `x-campaign Phase 15` outputs as host-side inputs:

- `docs/PARITY_REPORT.md`
- `docs/X_CAMPAIGN_COMPASS.md`
- `docs/phase-15-host-adoption-boundary.md`
- Cockpit consumption map
- endpoint recommendation matrix
- public API descriptors
- host mutation authorization checklist

These inputs describe integration seams. They are not executable routes, controllers, or mutation approvals.

## Host Ownership Boundary

x-change owns:

- Cockpit routes
- Cockpit controllers
- Inertia page props
- authorization
- redaction
- operator identity
- route naming
- API resource decisions
- request validation if a future mutation is explicitly authorized

x-campaign owns:

- package-side campaign planning seams
- package-side read models
- package-side public API descriptors
- package-side endpoint recommendations
- package-side mutation authorization checklist shape
- package-side parity report and package compass

## Authorized Slice 1A Work

Slice 1A is documentation and boundary protection only.

It may:

- record the host adoption boundary
- update the Cockpit Compass
- add tests proving the boundary exists
- decide the next recommended slice

It must not add production adapters, package calls, routes, controllers, or frontend UI changes.

## Explicit Non-Scope

- No campaign mutation endpoints
- No Pay Code generation through campaign
- No delivery dispatch
- No journal writes
- No action execution
- No feedback sends or retries
- No provider calls
- No wallet reads or writes
- No money movement
- No replacement of current Claim UX
- No hard Composer dependency on `x-campaign`

## Next Recommended Slice

```text
x-change Host Integration Slice 1B — Campaign Cockpit Read Model Contract
```

Slice 1B should define x-change-side DTOs/contracts for read-only campaign Cockpit adoption facts.

The default implementation should be null/not-wired.

Any optional `x-campaign` adapter should wait for a later slice and must degrade safely when unavailable.
