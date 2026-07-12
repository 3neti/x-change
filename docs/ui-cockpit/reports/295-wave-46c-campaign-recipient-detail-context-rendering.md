# Cockpit Wave 46C — Campaign Recipient Detail Context Rendering

## Purpose

Render safe campaign-recipient context on Pay Code Detail when the page receives a read-only campaign navigation context.

## Implemented

- Added `campaign_navigation_context` to Pay Code Detail page props.
- Added a visible `Campaign recipient context` card to Pay Code Detail.
- The card displays:
  - planning key;
  - execution ID;
  - recipient ID;
  - destination;
  - mutation boundary reason;
  - redaction payload policy.
- The card only renders when:
  - context is authorized;
  - context is read-only;
  - destination is `pay_code_detail`;
  - planning key and execution ID are present.

## Boundaries preserved

- No campaign mutation controls.
- No campaign routes.
- No bulk issuance.
- No distribution dispatch.
- No feedback delivery.
- No journal writes.
- No provider calls.
- No wallet movement.
- No unsafe payload rendering.

## Verification

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave46cCampaignRecipientDetailContextRenderingTest.php
```

## Result

Frontend and architecture coverage passed.

## Expected UI effect

When Pay Code Detail is opened with safe campaign-recipient query context, operators should see a new `Campaign recipient context` card under the page hero.

## Next checkpoint

`Cockpit Wave 46D — Campaign Recipient Distribution Context Rendering`.
