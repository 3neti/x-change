# Dashboard Header and Secondary Panels Copy Polish

Date: 2026-07-16

## Result

Polished the remaining dashboard header and secondary-panel copy so the screen reads more like an operator console and less like scaffolding.

## What changed

- Header defaults:
  - `Pending read model` became `Summary not connected`.
  - `Pending provider` became `Provider not connected`.
- Dashboard eyebrow:
  - `Dashboard Productization` became `Operator Dashboard`.
- Funding section:
  - `Liquidity Center` became `Funding Overview`.
  - `Money position comes first` became `Balance and funding position`.
- Claim section:
  - `Redemption Pipeline` became `Claim Progress`.
  - `Redemption status` became `Claim and redemption status`.
  - `No execution` became `Read-only`.
- Attention section:
  - `Risk and Expiry` became `Attention Queue`.
  - `Risk signals` became `Items needing review`.
  - Risk severities render as title-cased labels.
- Campaign section:
  - `Campaign Cockpit Adoption` became `Campaigns`.
  - `Read-only boundary` became `No campaign selected`.
  - `Mutation blocked` became `Campaign changes`.
- Remaining dashboard fallback `Pending ...` phrases now render as not-connected summaries.

## Boundary

This slice is presentation-only.

It does not change:

- read-model contracts;
- query parameter names;
- raw contract status values;
- activity storage;
- lifecycle truth;
- execution behavior;
- journal writes;
- x-action execution;
- x-feedback delivery;
- provider calls;
- campaign mutation;
- voucher mutation;
- wallet movement;
- public API behavior;
- unsafe payload redaction.

## Verification

Command:

```bash
npm run test:frontend -- CockpitDashboardHydration.test.ts CockpitDashboardFoundation.test.ts CockpitDashboardShell.test.ts CockpitLayout.test.ts CockpitReadOnlyScenarioValidation.test.ts
```

Result:

```text
5 passed
42 passed
```

## Next checkpoint

Manual browser acceptance for `/x/cockpit`, then select the next page-specific productization target.
