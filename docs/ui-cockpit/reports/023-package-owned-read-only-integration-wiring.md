# Host Integration Slice 1I — Package-Owned Read-Only Integration Wiring

## Status

Complete.

## Purpose

Move the read-only Settlement OS package wiring into `3neti/x-change` so the host application can remain dumb.

The host should not be responsible for knowing how Cockpit discovers or composes:

- x-journal evidence summaries
- x-action presentation-only CTA/action summaries
- x-feedback communication delivery summaries
- x-campaign campaign Cockpit summaries

## Decision

x-change now owns the Composer dependency wiring for the read-only integration packages:

- `3neti/x-journal`
- `3neti/x-action`
- `3neti/x-feedback`
- `3neti/x-campaign`

The adapter behavior remains fail-safe and read-only.

This supersedes the earlier Host Integration Slice 1C assumption that x-change should avoid hard Composer dependencies on these read-side packages.

## Implementation

- Added path repositories for the four local packages to `packages/x-change/composer.json`.
- Added the four packages to `packages/x-change` runtime requirements.
- Updated the x-change package Testbench harness to register the package providers when installed.
- Loaded read-side package migrations in the x-change package test environment where needed.
- Added focused coverage proving x-change can resolve and exercise real read-only adapters instead of only fake or fallback unavailable models.

## Boundaries Preserved

This slice does not add:

- host application logic
- host route/controller wiring
- mutation endpoints
- Pay Code generation through campaign
- campaign execution
- journal writes
- action execution
- feedback sends or retries
- provider calls
- wallet reads or writes
- money movement
- raw provider payload exposure

## Test Coverage

Focused test:

```text
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/OptionalCockpitRealPackageIntegrationTest.php
```

Result:

```text
1 passed, 31 assertions
```

Related regression subset:

```text
php -d memory_limit=1G vendor/bin/pest \
  tests/Unit/Architecture/CampaignCockpitOptionalAdapterBoundaryTest.php \
  tests/Unit/Architecture/CampaignCockpitReadModelContractTest.php \
  tests/Unit/Architecture/SettlementOsIntegrationReadinessReportTest.php \
  tests/Unit/Cockpit/CockpitReadModelBaselineTest.php
```

Result:

```text
25 passed, 266 assertions
```

## Risks

- `x-feedback` currently brings transport dependencies, including SMS and webhook sender packages, into x-change transitively. Cockpit adapters must continue to avoid delivery execution.
- Composer resolution updated several transitive package versions in `packages/x-change/composer.lock`. Keep focused regression coverage around Cockpit and lifecycle scenarios.
- The host app should still only consume x-change. It should not duplicate this integration wiring.

## Next Recommendation

Use these real adapters to hydrate read-only Cockpit surfaces from package-owned state.

Recommended next branch:

```text
x-change Host Integration Slice 2 — Journal/action/feedback read-model hydration into Cockpit surfaces
```
