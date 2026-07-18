# Selected Campaign Local Fixture Wiring — Slice 1

Date: 2026-07-18

## Result

Pass.

Cockpit can now hydrate a selected local campaign fixture through the real optional x-campaign read-only adapter when the local fixture is enabled and the operator opens:

`/x/cockpit?campaign_planning_key=plan-local&campaign_execution_id=exec-local`

## What Changed

- Added a non-production local campaign fixture configuration block.
- Seeded an x-campaign in-memory campaign plan through x-campaign contracts when:
  - the x-campaign package contracts/classes are installed,
  - local fixture configuration is enabled,
  - the requested planning key matches the configured fixture key,
  - and the selected campaign is absent from the in-memory repository.
- Preserved dynamic optional-package lookup so x-change does not hard-import x-campaign classes.
- Confirmed the selected campaign dashboard read model exposes:
  - package source: `x-campaign`,
  - planning key: `plan-local`,
  - execution id: `exec-local`,
  - campaign name: `Local Cockpit Campaign`,
  - operator-safe Quick Generate prefill context.

## Deferred

Recipient-level Quick Generate links remain deferred until the upstream x-campaign summary shape preserves recipient source contexts in a stable read-model contract.

## Boundary Confirmation

This slice does not add durable persistence, database migrations, routes, controllers, campaign mutation, campaign dispatch, Pay Code generation through campaign, feedback sends, journal writes, action execution, provider calls, wallet behavior, Treasury behavior, public API changes, or money movement.

## Verification

From `packages/x-change`:

```bash
vendor/bin/pest tests/Feature/Cockpit/CockpitDashboardRealIntegrationReadModelTest.php
```

Result: 3 passed, 78 assertions.

From the host root:

```bash
vendor/bin/pint --dirty --format agent packages/x-change/config/x-change.php packages/x-change/src/Services/Cockpit/OptionalCockpitIntegrationReadModels.php packages/x-change/tests/Feature/Cockpit/CockpitDashboardRealIntegrationReadModelTest.php
```

Result: passed.
