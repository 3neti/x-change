# Cockpit Wave 47C — Distribution Workspace Campaign Return Navigation

## Purpose

Add safe read-only return links from Distribution Workspace when the page has campaign-recipient navigation context.

## Implemented

Distribution Workspace now renders:

- `Return to Pay Code Detail · campaign context`
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
- Distribution dispatch remains disabled.
- No campaign mutation controls are added.
- No campaign routes/controllers are added.
- No bulk issuance, provider calls, wallet movement, feedback delivery, journal writes, or unsafe payload rendering is introduced.

## Verification

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave47cDistributionWorkspaceCampaignReturnNavigationTest.php
```

## Result

Frontend and architecture coverage passed.

## Expected UI effect

The Distribution Workspace `Campaign recipient context` card now includes three pills for returning to Pay Code Detail, Explorer, and Campaign Dashboard.

## Next checkpoint

`Cockpit Wave 47D — Campaign Destination Return Navigation Publish / Browser Verification`.
