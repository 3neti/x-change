# Host Integration Slice 1G — Campaign Cockpit Dedicated Read-Only Workspace Decision Point

## Decision

Decision: defer a dedicated Campaign Cockpit workspace route.

The existing Pay Code Explorer route remains the host navigation surface for campaign-derived operator context until a later slice explicitly authorizes a dedicated read-only campaign workspace.

## Rationale

Host Integration Slice 1F already provides a safe bridge from the dashboard campaign adoption panel into the existing Pay Code Explorer through `campaign_navigation_context`.

That is enough for the current read-only adoption goal:

- preserve one existing Cockpit route surface
- avoid creating a campaign route namespace before its read model responsibilities are clearer
- avoid implying campaign execution or campaign mutation capabilities
- keep x-change independent from x-campaign package internals
- keep operator context presentation-only

## Current Host Surface

The current host path remains:

```text
Dashboard campaign panel
    ↓
existing Pay Code Explorer route
    ↓
campaign_navigation_context
```

This context is presentation-only and must not be treated as campaign lifecycle truth.

## Deferred Workspace Criteria

A dedicated read-only campaign workspace route may be reconsidered only after a smaller future slice defines:

- workspace route name and URL
- read model contract for campaign workspace facts
- authorization and redaction policy
- empty/unavailable states
- pagination or collection semantics
- x-campaign adapter ownership
- route prop shape
- frontend presentation scope
- test coverage proving no mutation behavior

## Explicit Non-Goals

- No dedicated campaign workspace route
- No campaign route namespace
- No campaign mutation endpoints
- No Pay Code generation through campaign
- No delivery dispatch
- No journal writes
- No feedback sends or retries
- No wallet reads or writes
- No provider calls
- No money movement
- No x-campaign hard Composer dependency
- No x-campaign imports

## Boundary Result

No new route, controller, page, adapter, mutation path, provider call, queue dispatch, persistence layer, or package dependency is introduced in Slice 1G.

The correct next implementation path remains read-only hardening unless a later human-approved slice explicitly authorizes a dedicated workspace route.

## Verification

Red baseline:

```text
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CampaignCockpitDedicatedWorkspaceDecisionTest.php tests/Unit/Architecture/CampaignCockpitNavigationBoundaryTest.php
```

Result:

```text
2 failed, 4 passed, 23 assertions
```

## Next Recommended Slice

Host Integration Slice 1H should either:

1. close Campaign Cockpit host adoption as read-only complete for now, or
2. add a small route-planning document for a future dedicated read-only campaign workspace without implementing the route.

Do not add mutation behavior without a separate explicit mutation roadmap.
