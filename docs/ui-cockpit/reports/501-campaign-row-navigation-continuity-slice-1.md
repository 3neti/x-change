# Campaign-aware Row Navigation Continuity — Slice 1

Date: 2026-07-18

## Scope

Preserve campaign context when a campaign-aware Pay Code Explorer row opens Voucher Detail or Distribution Workspace.

## Result

- Explorer row action links now append campaign planning key, execution ID, campaign ID, audience ID, recipient ID, and campaign source when campaign navigation context is present.
- Existing row action query parameters are preserved.
- The behavior is presentation/navigation-only and does not make the rows campaign-owned.

## Verification

Command:

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitCampaignExplorerNavigation.test.ts
```

Result:

- 1 test file passed
- 6 tests passed

## Boundary

No campaign route, campaign controller, campaign mutation, campaign dispatch, Pay Code issuance through campaign, voucher lifecycle mutation, journal write, action execution, feedback delivery, provider call, wallet behavior, Treasury behavior, public API behavior, or money movement changed.
