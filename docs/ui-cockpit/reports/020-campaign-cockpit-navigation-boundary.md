# Host Integration Slice 1F — Campaign Cockpit Workspace / Explorer Read-Only Navigation Boundary

## Scope

This slice adds a read-only navigation boundary from the Campaign Cockpit dashboard presentation into existing Cockpit explorer surfaces.

It does not create a campaign route namespace. It only carries safe campaign context into the existing Pay Code Explorer route.

## Added Boundary

The dashboard campaign panel now exposes a read-only link to the existing Pay Code Explorer route:

```text
/x/cockpit/pay-codes
```

with optional query context:

```text
campaign_planning_key
campaign_execution_id
campaign_source=campaign_cockpit
```

The Pay Code Explorer receives this as:

```text
campaign_navigation_context
```

## Presentation Contract

`campaign_navigation_context` is presentation-only and includes:

```text
schema
status
authorized
source
planning_key
execution_id
destination
read_only
mutation
redactions
```

The context is rendered only as operator orientation. It does not execute search, filter server-side campaign data, generate Pay Codes, dispatch delivery, or mutate campaign state.

## Explicit Non-Goals

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

## Safety Rules

- The dashboard link targets only an existing read-only Cockpit route.
- The destination context must remain `read_only: true`.
- The explorer must not render raw campaign payloads, provider payloads, wallets, mutation routes, or package-internal campaign structures.
- Campaign workspace navigation remains disabled until an explicit read-only route is authorized.

## Verification

Red baseline:

```text
npm run test:frontend -- tests/frontend/cockpit/CockpitDashboardHydration.test.ts tests/frontend/cockpit/CockpitCampaignExplorerNavigation.test.ts
```

Result:

```text
3 failed, 9 passed
```

```text
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php --filter='campaign navigation context|does not register cockpit mutation routes' tests/Unit/Architecture/CampaignCockpitNavigationBoundaryTest.php
```

Result:

```text
1 failed, 1 passed
```

## Next Recommended Slice

Host Integration Slice 1G should decide whether the Campaign Cockpit read-only context needs a dedicated read-only workspace route or whether the existing Pay Code Explorer remains the correct host surface until mutation work is explicitly authorized.
