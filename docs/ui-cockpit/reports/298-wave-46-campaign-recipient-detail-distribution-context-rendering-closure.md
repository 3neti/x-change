# Cockpit Wave 46 — Campaign Recipient Detail / Distribution Context Rendering Closure

## Status

Completed.

## Completed checkpoints

- Wave 46A — Campaign Recipient Detail / Distribution Context Rendering Audit
- Wave 46B — Campaign Recipient Detail / Distribution Backend Prop Bridge
- Wave 46C — Campaign Recipient Detail Context Rendering
- Wave 46D — Campaign Recipient Distribution Context Rendering
- Wave 46E — Campaign Recipient Detail / Distribution Publish / Browser Verification
- Wave 46F — Campaign Recipient Detail / Distribution Context Rendering Closure

## Operator-visible result

When an operator opens Pay Code Detail or Distribution Workspace from a campaign-attributed Operator Issuance Activity card, the destination page now renders a `Campaign recipient context` card.

Pay Code Detail shows:

- planning key;
- execution ID;
- recipient ID;
- destination `pay_code_detail`;
- mutation boundary `campaign-navigation-read-only`;
- redaction policy `navigation-context-only`.

Distribution Workspace shows:

- planning key;
- execution ID;
- recipient ID;
- destination `distribution_workspace`;
- mutation boundary `campaign-navigation-read-only`;
- redaction policy `navigation-context-only`.

## Boundaries preserved

- No campaign mutation.
- No campaign routes/controllers.
- No bulk issuance.
- No distribution dispatch.
- No feedback delivery.
- No journal writes.
- No provider calls.
- No wallet movement.
- No lifecycle truth ownership.
- No unsafe campaign, provider, wallet, journal, action, or feedback payload rendering.

## Verification

```bash
cd packages/x-change
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php --filter="campaign recipient navigation context"
npm run test:frontend -- tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave46aCampaignRecipientDetailDistributionContextRenderingAuditTest.php tests/Unit/Architecture/CockpitWave46bCampaignRecipientDetailDistributionBackendPropBridgeTest.php tests/Unit/Architecture/CockpitWave46cCampaignRecipientDetailContextRenderingTest.php tests/Unit/Architecture/CockpitWave46dCampaignRecipientDistributionContextRenderingTest.php tests/Unit/Architecture/CockpitWave46eCampaignRecipientDetailDistributionPublishBrowserVerificationTest.php tests/Unit/Architecture/CockpitWave46CampaignRecipientDetailDistributionContextRenderingClosureTest.php
cd ../..
npm run test:browser -- tests/playwright/cockpit-campaign-activity-navigation.spec.ts
```

## Results

- Route feature coverage: 2 passed.
- Destination frontend coverage: 16 passed.
- Wave 46 architecture guards: 6 passed.
- Browser verification: 1 passed.

## Next recommended wave

`Cockpit Wave 47 — Campaign Recipient Destination Return Navigation`

Recommended scope:

- add safe read-only return links from Pay Code Detail to campaign-aware Explorer/Dashboard when `campaign_navigation_context` is present;
- add safe read-only return links from Distribution Workspace to Pay Code Detail and campaign-aware Explorer/Dashboard when `campaign_navigation_context` is present;
- preserve campaign-recipient query context;
- keep campaign mutation, distribution dispatch, bulk issuance, provider calls, wallet movement, feedback delivery, journal writes, and unsafe payload exposure blocked.
