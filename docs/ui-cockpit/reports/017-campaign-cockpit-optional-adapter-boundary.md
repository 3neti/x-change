# Host Integration Slice 1C — Campaign Cockpit Read Model Optional Adapter Boundary

This slice adds the optional x-change host-side adapter boundary for consuming campaign Cockpit adoption facts from an `x-campaign`-compatible service.

The adapter remains read-only and fail-safe.

## Adapter Seam

The adapter resolves a configured service ID from:

```text
x-change.cockpit.integrations.campaign.cockpit
```

When no configured service ID exists, it falls back to the default package FQCN string for:

```text
CampaignCockpitWorkspace::summary
```

Resolution is string-configured. The x-change package does not import x-campaign classes and does not require a hard Composer dependency on `x-campaign`.

## Implemented

- Added `OptionalCockpitIntegrationReadModels::campaignAdoption`.
- Added x-change mapping from a `CampaignCockpitWorkspace::summary`-compatible response into `CockpitCampaignReadModelData`.
- Added `facts` to `CockpitCampaignReadModelData` for redacted, read-only campaign summary facts.
- Updated `VoucherLifecycleCockpitReadModelProvider::forCampaignAdoption` to delegate to optional integrations when available.
- Preserved the null/not-wired provider for default contract behavior.

## Read-Only Mapping

The optional adapter maps:

- planning key
- execution id
- operator id
- cards
- panels
- actions
- blockers
- metadata

The adapter redacts sensitive fields before exposing facts to Cockpit.

Mutation remains blocked even if the package-side read model exposes action descriptors.

## Failure Behavior

The optional adapter degrades to:

```text
status: unavailable
authorized: false
source: x-campaign
```

Failure reasons are exposed without leaking exception messages.

## Explicit Non-Scope

- No hard Composer dependency on `x-campaign`
- No x-campaign imports
- No campaign mutation endpoints
- No Pay Code generation through campaign
- No delivery dispatch
- No journal writes
- No action execution
- No feedback sends or retries
- No provider calls
- No wallet reads or writes
- No money movement
- No Cockpit routes or controllers
- No frontend campaign pages

## Initial Red Baseline

```text
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/CockpitReadModelBaselineTest.php --filter='campaign cockpit adoption' tests/Unit/Architecture/CampaignCockpitOptionalAdapterBoundaryTest.php

Result: 2 failed, 1 assertion
```

Expected failures:

- `VoucherLifecycleCockpitReadModelProvider::forCampaignAdoption` still returned the fallback provider.
- `OptionalCockpitIntegrationReadModels::campaignAdoption` did not exist.

## Final Verification

```text
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/CockpitReadModelBaselineTest.php tests/Unit/Architecture/CampaignCockpitOptionalAdapterBoundaryTest.php tests/Unit/Architecture/CampaignCockpitReadModelContractTest.php tests/Unit/Architecture/CampaignCockpitAdoptionBoundaryPlanTest.php

Result: 25 passed, 266 assertions
```

```text
composer validate --strict

Result: ./composer.json is valid
```

```text
../../vendor/bin/pint --dirty --format agent

Result: passed
```

```text
php -d memory_limit=1G vendor/bin/pest

Result: 1030 passed, 5 skipped, 5644 assertions
```

## Next Recommended Slice

```text
x-change Host Integration Slice 1D — Campaign Cockpit Read Model Route Prop Boundary
```

Slice 1D should expose the campaign read model as a read-only Inertia prop on an existing or explicitly planned Cockpit surface.

It must not introduce campaign mutations.
