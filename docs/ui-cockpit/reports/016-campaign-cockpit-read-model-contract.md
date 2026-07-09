# Host Integration Slice 1B — Campaign Cockpit Read Model Contract

This slice adds the x-change-side campaign Cockpit read model contract for read-only campaign adoption.

The contract schema is:

```text
x-change.cockpit.campaign-adoption.v1
```

## Implemented

- Added `CockpitCampaignReadModelData`.
- Extended `CockpitReadModelProviderContract` with `forCampaignAdoption`.
- Added the `NullCockpitReadModelProvider` default implementation.
- Kept `VoucherLifecycleCockpitReadModelProvider` compatible by delegating campaign adoption facts to the null provider.
- Added tests for the default not-wired shape and documentation/Compass guardrails.

## Default Behavior

The default read model is intentionally:

- `status: not_wired`
- `authorized: false`
- `source: null-campaign-cockpit-read-model-provider`
- read-only for every campaign adoption surface
- blocked for campaign mutation
- redacted with no package payload loaded

## Surfaces Reserved

- campaign dashboard
- campaign explorer
- audience import workspace
- attachment operator workspace
- campaign API descriptors

These are reserved read-model surfaces only. They do not register routes, controllers, jobs, or frontend pages.

## Explicit Non-Scope

- No x-campaign adapter
- No hard Composer dependency on `x-campaign`
- No campaign mutation endpoints
- No Pay Code generation through campaign
- No delivery dispatch
- No journal writes
- No action execution
- No feedback sends or retries
- No provider calls
- No wallet reads or writes
- No money movement

## Architectural Decision

x-change owns the host Cockpit contract shape, authorization, redaction, and route/page ownership.

x-campaign remains the owner of campaign planning and package-side read model semantics.

The first optional adapter must be added in a later slice and must degrade safely when x-campaign is unavailable.

## Tests

Initial red baseline:

```text
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/CockpitReadModelBaselineTest.php --filter='campaign cockpit read model|binds the cockpit read model|direct integration package' tests/Unit/Architecture/CampaignCockpitReadModelContractTest.php

Result: 2 failed, 2 passed, 6 assertions
```

Expected failures:

- `NullCockpitReadModelProvider::forCampaignAdoption()` did not exist.
- `docs/ui-cockpit/reports/016-campaign-cockpit-read-model-contract.md` did not exist.

Final verification:

```text
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/CockpitReadModelBaselineTest.php tests/Unit/Architecture/CampaignCockpitAdoptionBoundaryPlanTest.php tests/Unit/Architecture/CampaignCockpitReadModelContractTest.php tests/Unit/Architecture/SettlementOsIntegrationReadinessReportTest.php

Result: 23 passed, 181 assertions
```

```text
composer validate --strict

Result: ./composer.json is valid
```

```text
../../vendor/bin/pint --dirty --format agent

Result: fixed style/import order for modified PHP files
```

```text
php -d memory_limit=1G vendor/bin/pest

Result: 1026 passed, 5 skipped, 5546 assertions
```

## Next Recommended Slice

```text
x-change Host Integration Slice 1C — Campaign Cockpit Read Model Optional Adapter Boundary
```

Slice 1C should define the optional adapter boundary for consuming x-campaign read models only when available.

It should remain read-only and must not introduce campaign mutation endpoints.
