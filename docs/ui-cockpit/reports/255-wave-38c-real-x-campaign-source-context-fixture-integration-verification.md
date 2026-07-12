# Cockpit Wave 38C — Real x-campaign Source Context Fixture / Integration Verification

## Status

Completed.

## Scope

Verify that x-change can consume real `x-campaign` Cockpit summary DTO output as a read-only source context for Campaign → Quick Generate entry links.

This slice remains non-mutating:

- no campaign mutation
- no bulk issuance
- no delivery dispatch
- no provider calls
- no wallet movement
- no bypass of the existing `GeneratePayCode` handoff

## Implementation

`OptionalCockpitIntegrationReadModels` now normalizes both snake_case and camelCase campaign summary keys:

- `planning_key` / `planningKey`
- `execution_id` / `executionId`
- `operator_id` / `operatorId`

This matches the real `LBHurtado\XCampaign\Data\CampaignCockpitSummaryData` constructor shape while preserving compatibility with existing array fixtures.

## Tests

Added coverage to `OptionalCockpitRealPackageIntegrationTest` proving that:

- the real `CampaignCockpitSummaryData` class is available through x-change package wiring;
- camelCase DTO keys hydrate x-change's canonical snake_case Cockpit facts;
- safe `quick_generate_context` metadata survives as read-only Campaign source context;
- campaign mutation, Pay Code issuance, feedback delivery, journal writes, and money movement remain false.

## Commands

```bash
cd packages/x-change
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/OptionalCockpitRealPackageIntegrationTest.php tests/Feature/Cockpit/CockpitCampaignQuickGenerateSourceLinkTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php
```

Result:

```text
25 passed, 350 assertions
```

## UI effect

No new UI component.

The existing Campaign Cockpit Adoption `Open Quick Generate` link is now safer against real adapter DTO shape differences. When x-campaign provides safe Quick Generate metadata, Cockpit can use it even if no explicit dashboard query draft values are present.

## Next checkpoint

Cockpit Wave 38D — Campaign Real Adapter Source Link UI / Browser Verification.
