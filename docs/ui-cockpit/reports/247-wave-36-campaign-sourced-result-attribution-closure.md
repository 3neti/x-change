# Cockpit Wave 36 — Campaign-Sourced Quick Generate Result Attribution / Explorer Bridge Closure

## Status

Completed.

## Mission

Close the wave that carries campaign context from a campaign-prefilled Quick Generate submit into the successful operator result and campaign-aware navigation handoff.

## Completed slices

- Wave 36A — Campaign-Sourced Result Attribution / Explorer Bridge Audit.
- Wave 36B — Campaign Attribution Response Contract / Backend Handoff Links.
- Wave 36C — Campaign Attribution Result UI Presentation.
- Wave 36D — Campaign Attribution Browser / Publish Verification.

## As-built behavior

Campaign-prefilled Quick Generate now:

- accepts campaign context from query parameters.
- submits read-only campaign metadata through the existing issuance handoff.
- returns safe `campaign_attribution` on success.
- renders a `Campaign attribution` result card.
- exposes `Return to Campaign Explorer`.
- exposes `Return to Campaign Dashboard`.

## Boundaries preserved

- `GeneratePayCode` remains the issuance owner.
- Cockpit does not mutate campaigns.
- Cockpit does not execute campaign jobs.
- Cockpit does not perform bulk issuance.
- Cockpit does not deliver feedback.
- Cockpit does not call providers outside the existing issuance path.
- Cockpit does not expose raw campaign, recipient, provider, wallet, or balance payloads.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave36aCampaignSourcedResultAttributionAuditTest.php tests/Feature/Cockpit/CockpitQuickGenerateCampaignAttributionResponseTest.php tests/Unit/Architecture/CockpitWave36dBrowserPublishVerificationTest.php tests/Unit/Architecture/CockpitWave36ClosureTest.php
npx vitest run tests/frontend/cockpit/CockpitQuickGenerateFoundation.test.ts
npx playwright test tests/playwright/cockpit-quick-generate-campaign-context.spec.ts
php artisan x-change:doctor --assets --json
```

## Expected UI

After a campaign-prefilled Quick Generate succeeds, the result panel should show:

- `Campaign attribution`.
- originating planning/execution/campaign identifiers.
- generated Pay Code.
- `Return to Campaign Explorer`.
- `Return to Campaign Dashboard`.

## Next recommended wave

Cockpit Wave 37 — Campaign Context Source Link Generation / Campaign Surface Entry Points.

Purpose: generate the campaign-aware Quick Generate URL from real campaign/Cockpit surfaces so operators do not need to manually paste query parameters.
