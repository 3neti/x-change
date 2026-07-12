# Cockpit Wave 47B — Pay Code Detail Campaign Return Navigation

## Purpose

Add safe read-only return links from Pay Code Detail when the page has campaign-recipient navigation context.

## Implemented

Pay Code Detail now renders:

- `Return to Explorer · campaign context`
- `Return to Campaign Dashboard · read-only`

The links preserve:

- campaign planning key;
- campaign execution ID;
- campaign ID;
- campaign audience ID;
- campaign recipient ID;
- campaign source;
- activity code/source for Explorer return.

## Guardrails

- Links render only when the existing safe `campaign_navigation_context` renders.
- No campaign mutation controls are added.
- No campaign routes/controllers are added.
- No bulk issuance, distribution dispatch, provider calls, wallet movement, feedback delivery, journal writes, or unsafe payload rendering is introduced.

## Verification

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave47bPayCodeDetailCampaignReturnNavigationTest.php
```

## Result

Frontend and architecture coverage passed.

## Expected UI effect

The Pay Code Detail `Campaign recipient context` card now includes two pills for returning to Explorer and Campaign Dashboard.

## Next checkpoint

`Cockpit Wave 47C — Distribution Workspace Campaign Return Navigation`.
