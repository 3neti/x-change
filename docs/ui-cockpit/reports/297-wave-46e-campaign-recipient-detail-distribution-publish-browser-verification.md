# Cockpit Wave 46E — Campaign Recipient Detail / Distribution Publish / Browser Verification

## Purpose

Verify that the campaign-recipient context cards render in the host-published Pay Code Detail and Distribution Workspace pages in a real browser.

## Implemented verification

- Published package Cockpit assets into the host app.
- Verified published asset drift with the x-change asset doctor.
- Extended the campaign activity Playwright smoke to:
  - open a campaign-attributed Operator Issuance Activity card;
  - follow `Open Pay Code · campaign context · read-only`;
  - assert the Pay Code Detail campaign-recipient context card;
  - return to the dashboard;
  - follow `Open Distribution workspace · campaign context · read-only`;
  - assert the Distribution Workspace campaign-recipient context card.

## Commands

```bash
php artisan x-change:install --force
php artisan x-change:doctor --assets --json
npm run test:browser -- tests/playwright/cockpit-campaign-activity-navigation.spec.ts
cd packages/x-change
npm run test:frontend -- tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave46eCampaignRecipientDetailDistributionPublishBrowserVerificationTest.php
```

## Results

- Asset doctor: checked 58, ok 58, stale 0, missing 0, extra 0.
- Playwright: 1 passed.
- Frontend destination rendering: 16 passed.
- Architecture guard: 1 passed.

## Expected UI effect

From `/x/cockpit`, a campaign-attributed activity card now lets the operator open:

- Pay Code Detail with a `Campaign recipient context` card.
- Distribution Workspace with a `Campaign recipient context` card.

Both cards remain read-only and render only safe navigation context.

## Next checkpoint

`Cockpit Wave 46F — Campaign Recipient Detail / Distribution Context Rendering Closure`.
