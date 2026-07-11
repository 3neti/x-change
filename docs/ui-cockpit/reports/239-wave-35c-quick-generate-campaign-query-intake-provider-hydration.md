# Wave 35C — Quick Generate Campaign Query Intake / Provider Hydration

## Status

Completed.

## Mission

Allow the Quick Generate page to receive a safe campaign planning context through query parameters and hydrate it into the existing Quick Generate read model.

## Added

- Query intake on the Quick Generate Cockpit controller.
- Campaign context fields on `CockpitReadModelQueryData`.
- Provider hydration into `quick_generate_read_model.campaign_context`.
- Feature coverage for safe hydrated campaign context.

## Boundary

This slice is prefill-only. It does not create campaigns, mutate campaign state, execute bulk issuance, deliver feedback, call providers, reserve funds, or bypass the existing `GeneratePayCode` handoff.

## UI impact

No visible UI change yet. The hydrated campaign context becomes visible in Wave 35D when the Vue Quick Generate page consumes the read model.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitQuickGenerateCampaignContextHydrationTest.php
```

## Next

Cockpit Wave 35D — Quick Generate Campaign Prefill UI Presentation.
