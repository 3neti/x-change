# Cockpit Wave 38 — Campaign Workspace Entry Point Real Adapter / x-campaign Source Context Adoption Closure

## Status

Completed.

## Mission

Allow x-change Cockpit to consume safe x-campaign Cockpit summary metadata as Campaign → Quick Generate source-link context.

## Completed slices

- Wave 38A — Campaign Workspace Entry Point Real Adapter Audit.
- Wave 38B — Campaign Adapter Source Context Normalization.
- Wave 38C — Real x-campaign Source Context Fixture / Integration Verification.
- Wave 38D — Campaign Real Adapter Source Link UI / Browser Verification.

## As-built result

x-change can now hydrate `campaign_read_model.quick_generate_link` from safe x-campaign summary metadata when explicit dashboard query draft values are absent.

The adapter supports real x-campaign DTO key shape by canonicalizing camelCase `CampaignCockpitSummaryData` fields into x-change's Cockpit facts:

- `planningKey` → `planning_key`
- `executionId` → `execution_id`
- `operatorId` → `operator_id`

Existing explicit query values still win over adapter metadata. This keeps manually supplied links deterministic while letting the real x-campaign adapter become the default source when available.

## Expected UI result

No new component was added in Wave 38.

The existing Campaign Cockpit Adoption panel still shows `Open Quick Generate`. When x-campaign provides safe source metadata, that link can open `/x/cockpit/quick-generate` with the Campaign context prefill populated.

## Preserved boundaries

- No campaign mutation.
- No bulk issuance.
- No delivery dispatch.
- No provider calls.
- No direct wallet access or money movement.
- No journal/action/feedback mutation.
- No new campaign routes or controllers.
- No raw campaign, recipient, provider, wallet, import, delivery, or generation payload exposure.
- `GeneratePayCode` remains the issuance owner.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitCampaignQuickGenerateSourceLinkTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/OptionalCockpitRealPackageIntegrationTest.php tests/Feature/Cockpit/CockpitCampaignQuickGenerateSourceLinkTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php
php artisan x-change:install --force
php artisan x-change:doctor --assets --json
npx playwright test tests/playwright/cockpit-campaign-source-link.spec.ts
```

Results:

- focused PHP tests passed;
- `x-change:install --force` passed;
- asset doctor passed with `checked 58, ok 58, stale 0, missing 0, extra 0`;
- Playwright browser smoke passed.

## Next recommended wave

Cockpit Wave 39 — Campaign Plan-to-Issuance Draft Template Mapping.

Recommended scope:

- consume campaign plan/recipient intent as a typed issuance draft input;
- map campaign template intent to Quick Generate template keys;
- keep x-campaign as context/planning source only;
- keep `GeneratePayCode` as the issuance owner;
- keep campaign mutation, bulk issuance, delivery, provider calls, and wallet movement separately gated.
