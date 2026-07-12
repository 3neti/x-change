# Cockpit Wave 46D — Campaign Recipient Distribution Context Rendering

## Purpose

Render safe campaign-recipient context on Distribution Workspace when the page receives a read-only campaign navigation context.

## Implemented

- Added `campaign_navigation_context` to Distribution Workspace page props.
- Added a visible `Campaign recipient context` card to Distribution Workspace.
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
  - destination is `distribution_workspace`;
  - planning key and execution ID are present.

## Boundaries preserved

- No distribution dispatch.
- No campaign mutation controls.
- No campaign routes.
- No bulk issuance.
- No feedback delivery.
- No journal writes.
- No provider calls.
- No wallet movement.
- No unsafe payload rendering.

## Verification

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave46dCampaignRecipientDistributionContextRenderingTest.php
```

## Result

Frontend and architecture coverage passed.

## Expected UI effect

When Distribution Workspace is opened with safe campaign-recipient query context, operators should see a new `Campaign recipient context` card under the page hero.

## Next checkpoint

`Cockpit Wave 46E — Campaign Recipient Detail / Distribution Publish / Browser Verification`.
