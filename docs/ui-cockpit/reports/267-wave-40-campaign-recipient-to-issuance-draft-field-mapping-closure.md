# Cockpit Wave 40 — Campaign Recipient-to-Issuance Draft Field Mapping Closure

## Status

Completed.

## Mission

Normalize safe campaign recipient/payout fields into existing Cockpit Quick Generate draft semantics without creating campaign mutation or bulk issuance.

## Completed slices

- Wave 40A — Campaign Recipient-to-Issuance Draft Field Mapping Audit.
- Wave 40B — Campaign Recipient Field Normalizer / Draft Adapter.
- Wave 40C — Campaign Source Link Recipient Field Propagation.
- Wave 40D — Campaign Recipient Field Browser / Published Asset Verification.

## As-built result

Campaign recipient/payout source context can now hydrate the existing Quick Generate draft path with:

- amount;
- currency;
- recipient reference;
- feedback mobile/email for draft metadata;
- purpose;
- rider message;
- source-link purpose/message fallback.

The Campaign `Open Quick Generate` source-link path can carry safe amount, recipient reference, and purpose/message values into `/x/cockpit/quick-generate`.

Mobile and email remain off the source-link URL.

## UI result

No new component.

Operators continue using the existing Campaign Cockpit Adoption `Open Quick Generate` link. The improvement is functional: campaign adapter metadata can now prefill Quick Generate from recipient/payout context.

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
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave40aCampaignRecipientFieldMappingAuditTest.php
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/DefaultCockpitCampaignIssuanceDraftAdapterTest.php tests/Unit/Cockpit/DefaultCockpitIssuanceDraftCompilerTest.php tests/Unit/Cockpit/DefaultCockpitQuickGenerateDraftFactoryTest.php
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitCampaignQuickGenerateSourceLinkTest.php tests/Unit/Cockpit/DefaultCockpitCampaignIssuanceDraftAdapterTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php
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

Cockpit Wave 41 — Campaign Recipient Source-Link Selection / Operator Entry Point.

Recommended scope:

- expose a read-only recipient/source-link entry point from campaign read-model facts;
- generate one-recipient Quick Generate links from selected safe recipient context;
- keep Quick Generate as the mutation boundary;
- keep campaign mutation, bulk issuance, delivery, provider calls, wallet movement, journal/action/feedback mutation, and raw payload exposure separately gated.
