# Pay Code Explorer Campaign Filter Affordance — Slice 1

Date: 2026-07-18

## Scope

Make campaign-origin context behave like a visible, preserved read-only Explorer filter context.

## Result

- `/x/cockpit/pay-codes` now adds campaign context facts to the filter summary when campaign navigation context is present.
- Search and status filter GET submissions preserve campaign planning key, execution ID, campaign ID, audience ID, recipient ID, and source through hidden fields.
- Clear-filter links now clear only search/status filters while keeping the campaign orientation context.

## Verification

Command:

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitCampaignExplorerNavigation.test.ts
```

Result:

- 1 test file passed
- 5 tests passed

## Boundary

No campaign route, campaign controller, campaign mutation, campaign dispatch, Pay Code issuance through campaign, journal write, action execution, feedback delivery, provider call, wallet behavior, Treasury behavior, public API behavior, or money movement changed.
