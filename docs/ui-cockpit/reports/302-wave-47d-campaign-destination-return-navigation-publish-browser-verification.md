# Cockpit Wave 47D — Campaign Destination Return Navigation Publish / Browser Verification

## Purpose

Verify that campaign destination return links are published into the host app and work as browser-rendered links.

## Implemented verification

- Published package Cockpit assets into the host app.
- Verified published asset drift with the x-change asset doctor.
- Extended the campaign activity Playwright smoke to assert:
  - Pay Code Detail return links to Explorer and Campaign Dashboard;
  - Distribution Workspace return links to Pay Code Detail, Explorer, and Campaign Dashboard;
  - preserved `campaign_recipient_id`;
  - preserved `activity_code` for Explorer returns;
  - read-only/campaign-context labels.

## Commands

```bash
php artisan x-change:install --force
php artisan x-change:doctor --assets --json
npm run test:browser -- tests/playwright/cockpit-campaign-activity-navigation.spec.ts
cd packages/x-change
npm run test:frontend -- tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave47dCampaignDestinationReturnNavigationPublishBrowserVerificationTest.php
```

## Results

- Asset doctor: checked 58, ok 58, stale 0, missing 0, extra 0.
- Playwright: 1 passed.
- Frontend destination return navigation: 16 passed.
- Architecture guard: 1 passed.

## Expected UI effect

Campaign-aware Pay Code Detail and Distribution Workspace destination cards now include read-only return navigation pills.

## Next checkpoint

`Cockpit Wave 47E — Campaign Recipient Destination Return Navigation Closure`.
