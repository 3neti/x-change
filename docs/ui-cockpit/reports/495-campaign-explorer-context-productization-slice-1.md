# Campaign Explorer Context Productization — Slice 1

Date: 2026-07-18

## Scope

Productize the Pay Code Explorer campaign navigation context panel.

## Result

- `/x/cockpit/pay-codes` now renders campaign navigation context as an operator-facing `Campaign Explorer Context` panel when campaign query context is present.
- The panel shows the read-only campaign orientation facts: planning key, execution ID, campaign ID, audience ID, recipient ID, source, destination, and payload policy.
- The panel includes a safe return link back to the Cockpit campaign view with the same campaign context.
- Campaign changes remain disabled from the Explorer.

## Verification

Command:

```bash
npm run test:frontend -- tests/frontend/cockpit/CockpitCampaignExplorerNavigation.test.ts
```

Result:

- 1 test file passed
- 4 tests passed

## Boundary

No campaign route, campaign controller, campaign mutation, campaign dispatch, Pay Code issuance through campaign, journal write, action execution, feedback delivery, provider call, wallet behavior, Treasury behavior, public API behavior, or money movement changed.
