# Cockpit Wave 35B — Campaign Context Quick Generate Read Model Contract

## Mission

Add a safe Quick Generate read-model contract for campaign context and prefillable campaign draft facts.

## Added

- `CockpitQuickGenerateCampaignContextData`.
- `CockpitQuickGenerateReadModelData::campaign_context`.

## Contract

The read model can carry:

- campaign planning key;
- execution id;
- campaign id;
- audience id;
- recipient id;
- source;
- a safe `CockpitIssuanceDraftData` draft;
- explicit `mutates_campaign: false`.

## Boundaries

This slice adds no query intake, no UI, no campaign mutation, no bulk issuance, no delivery, no provider calls, no wallet mutation, and no unsafe payload exposure.

## Expected UI Result

No visible UI change until provider hydration and Vue adoption.

## Verification

- `php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/CockpitQuickGenerateCampaignContextContractTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php --filter='quick generate|campaign context'`

## Next Slice

Cockpit Wave 35C — Quick Generate Campaign Query Intake / Provider Hydration.
