# Cockpit Wave 39 — Campaign Plan-to-Issuance Draft Template Mapping Closure

## Status

Completed.

## Mission

Normalize campaign plan/template intent into existing Cockpit Quick Generate draft semantics without creating a separate campaign issuance runtime.

## Completed slices

- Wave 39A — Campaign Plan-to-Issuance Draft Template Mapping Audit.
- Wave 39B — Campaign Template Intent Normalizer / Draft Adapter.
- Wave 39C — Campaign Source Link Template Intent Propagation.
- Wave 39D — Campaign Template Intent Browser / Published Asset Verification.

## As-built result

Campaign plan/source context can now express template intent using campaign/product language, and x-change maps it into existing Quick Generate template keys.

Examples:

- `money_changer` → `money-changer`
- `branch-cash-out` → `money-changer`
- `ofw_remittance` → `ofw-remittance`
- `remittance` → `ofw-remittance`
- `settlement-envelope` → `settlement-envelope`

The same adapter path is used for:

- campaign-to-draft conversion;
- Campaign Cockpit Adoption `Open Quick Generate` source-link construction.

Explicit `template_key` remains authoritative.

## UI result

No new component.

Operators continue using the existing Campaign Cockpit Adoption `Open Quick Generate` link. The improvement is functional: campaign adapter metadata can now select the correct Quick Generate template through safe template intent.

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
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave39aCampaignPlanToIssuanceDraftTemplateMappingAuditTest.php
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/DefaultCockpitCampaignIssuanceDraftAdapterTest.php tests/Unit/Cockpit/DefaultCockpitIssuanceDraftCompilerTest.php tests/Unit/Cockpit/DefaultCockpitQuickGenerateDraftFactoryTest.php
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitCampaignQuickGenerateSourceLinkTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php tests/Unit/Cockpit/DefaultCockpitCampaignIssuanceDraftAdapterTest.php
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

Cockpit Wave 40 — Campaign Recipient-to-Issuance Draft Field Mapping.

Recommended scope:

- normalize safe campaign recipient data into recipient reference, feedback mobile/email, rider message, amount, and purpose;
- keep campaign context as source/prefill metadata only;
- preserve existing Quick Generate and `GeneratePayCode` ownership;
- keep campaign mutation, bulk issuance, provider calls, delivery, wallet movement, journal/action/feedback mutation, and raw payload exposure separately gated.
