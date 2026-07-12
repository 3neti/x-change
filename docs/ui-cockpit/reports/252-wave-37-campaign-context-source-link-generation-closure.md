# Cockpit Wave 37 — Campaign Context Source Link Generation / Campaign Surface Entry Points Closure

## Status

Completed.

## Mission

Close the source-entry side of the campaign-to-Quick-Generate loop.

## Completed slices

- Wave 37A — Campaign Context Source Link Generation Audit.
- Wave 37B — Campaign Quick Generate Source Link Read Model Contract / Hydration.
- Wave 37C — Campaign Quick Generate Source Link UI Presentation.
- Wave 37D — Campaign Quick Generate Source Link Browser / Publish Verification.

## As-built result

Campaign-aware Cockpit dashboard context now hydrates `campaign_read_model.quick_generate_link` with a full, safe Quick Generate URL.

The Campaign Cockpit Adoption panel can render `Open Quick Generate`. Clicking it opens the existing `/x/cockpit/quick-generate` route with campaign context prefill values.

## Preserved boundaries

- No campaign mutation.
- No bulk issuance.
- No delivery planning or feedback dispatch.
- No provider calls outside the existing issuance path.
- No wallet mutation outside the existing issuance path.
- No campaign routes or controllers were added.
- No raw campaign, recipient, provider, wallet, balance, import, or generation payload exposure.
- `GeneratePayCode` remains the issuance owner.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitCampaignQuickGenerateSourceLinkTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php tests/Unit/Architecture/CockpitWave37aCampaignContextSourceLinkGenerationAuditTest.php tests/Unit/Architecture/CockpitWave37dBrowserPublishVerificationTest.php
npx vitest run tests/frontend/cockpit/CockpitDashboardHydration.test.ts
php artisan x-change:doctor --assets --json
npx playwright test tests/playwright/cockpit-campaign-source-link.spec.ts
```

## Expected UI result

When `/x/cockpit` is opened with campaign query context, Campaign Cockpit Adoption can show `Open Quick Generate`. Clicking it opens Quick Generate with `Campaign context prefill`, template, amount, recipient reference, and purpose values populated.

## Next recommended wave

Cockpit Wave 38 — Campaign Workspace Entry Point Real Adapter / x-campaign Source Context Adoption.
