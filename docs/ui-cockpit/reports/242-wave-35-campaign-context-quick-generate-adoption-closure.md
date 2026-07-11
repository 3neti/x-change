# Cockpit Wave 35 — Campaign Context Quick Generate Adoption Closure

## Status

Completed.

## Mission

Close the campaign-context Quick Generate adoption wave.

## Completed slices

- Wave 35A — Campaign Context Quick Generate Adoption Audit.
- Wave 35B — Campaign Context Quick Generate Read Model Contract.
- Wave 35C — Quick Generate Campaign Query Intake / Provider Hydration.
- Wave 35D — Quick Generate Campaign Prefill UI Presentation.
- Wave 35E — Campaign Context Quick Generate Browser / Publish Verification.

## As-built behavior

Quick Generate can now be opened with campaign query parameters. The page hydrates a safe campaign context, renders a `Campaign context prefill` card, prefills the operator form from the campaign draft, and submits read-only campaign metadata through the existing Quick Generate issuance handoff.

## Boundaries preserved

- Campaign context does not mutate campaign state.
- Campaign context does not execute bulk issuance.
- Campaign context does not deliver feedback.
- Campaign context does not call providers.
- Campaign context does not reserve funds or move money.
- Campaign context does not bypass `GeneratePayCode`.
- Raw campaign, recipient, provider, wallet, and balance payloads remain excluded.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave35aCampaignContextQuickGenerateAuditTest.php tests/Unit/Cockpit/CockpitQuickGenerateCampaignContextContractTest.php tests/Feature/Cockpit/CockpitQuickGenerateCampaignContextHydrationTest.php tests/Unit/Architecture/CockpitWave35eBrowserPublishVerificationTest.php
npx vitest run tests/frontend/cockpit/CockpitQuickGenerateFoundation.test.ts
npx playwright test tests/playwright/cockpit-quick-generate-campaign-context.spec.ts
php artisan x-change:doctor --assets --json
```

## UI result

Operators can verify this manually at:

```text
/x/cockpit/quick-generate?campaign_planning_key=plan-local&campaign_execution_id=exec-local&campaign_id=campaign-local&campaign_source=campaign_cockpit&campaign_template_key=ofw-remittance&campaign_amount=500.00&campaign_currency=PHP&campaign_recipient_reference=09173011987&campaign_purpose=Campaign%20payout
```

Expected visible result:

- `Campaign context prefill` card.
- Template set to `OFW Remittance`.
- Amount set to `500.00`.
- Recipient/reference set.
- Purpose set.

## Next recommended wave

Cockpit Wave 36 — Campaign-Sourced Quick Generate Result Attribution / Explorer Bridge.

Purpose: after a campaign-prefilled Quick Generate succeeds, preserve campaign attribution in the operator result and navigation handoff so the operator can return to campaign-aware Explorer/Dashboard context without campaign mutation.
