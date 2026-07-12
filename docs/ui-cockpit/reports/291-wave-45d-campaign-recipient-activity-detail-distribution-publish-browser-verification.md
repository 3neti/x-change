# Cockpit Wave 45D — Campaign Recipient Activity Detail / Distribution Publish / Browser Verification

## Purpose

Verify that the Wave 45 campaign-recipient activity detail and Distribution context bridge is published into the host app and exercised in a real browser.

## Scope

- Published package Cockpit assets into the host app.
- Verified published asset drift with the x-change asset doctor.
- Extended the existing Playwright campaign activity navigation smoke to assert:
  - `Open Pay Code · campaign context · read-only`;
  - `Open Distribution workspace · campaign context · read-only`;
  - campaign recipient context query propagation to Pay Code detail;
  - campaign recipient context query propagation to Distribution workspace.

## Commands

```bash
php artisan x-change:install --force
php artisan x-change:doctor --assets --json
npm run test:browser -- tests/playwright/cockpit-campaign-activity-navigation.spec.ts
cd packages/x-change
npm run test:frontend -- tests/frontend/cockpit/CockpitDashboardHydration.test.ts
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave45dCampaignRecipientActivityDetailDistributionPublishBrowserVerificationTest.php
```

## Results

- Asset doctor: checked 58, ok 58, stale 0, missing 0, extra 0.
- Playwright: 1 passed.
- Frontend hydration: 21 passed.
- Architecture guard: 1 passed.

## Browser assertion

The browser test seeds a safe campaign-attributed durable activity for `PC-PLAYWRIGHT-44` and verifies dashboard activity links:

```text
Open Pay Code · campaign context · read-only
Open Distribution workspace · campaign context · read-only
```

Both links preserve `campaign_recipient_id=recipient-playwright-44`.

## Boundaries preserved

- No campaign mutation.
- No bulk issuance.
- No delivery.
- No lifecycle truth ownership.
- No provider call.
- No wallet movement.
- No unsafe payload exposure.
- Existing Quick Generate issuance ownership remains with `GeneratePayCode`.

## Next checkpoint

`Cockpit Wave 45E — Campaign Recipient Activity Detail / Distribution Context Bridge Closure`.
