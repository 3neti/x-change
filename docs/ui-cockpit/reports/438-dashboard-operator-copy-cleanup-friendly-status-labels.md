# Dashboard Operator Copy Cleanup — Friendly Status Labels

Date: 2026-07-16

## Result

Replaced architectural dashboard status labels with operator-friendly copy.

## What changed

- `integration read-models not wired` became `Journal, action, and feedback summaries not connected yet`.
- `safe navigation` became `Links only`.
- `presentation-only` became `Read-only` in the Issuance Activity badge.
- `Read model pending` became `Balance summary not connected yet`.
- `missing-campaign-context` renders as `No campaign selected`.

## Boundary

This slice is copy-only.

It does not change:

- read-model contracts;
- activity storage;
- activity filters;
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
npm run test:frontend -- CockpitDashboardHydration.test.ts CockpitDashboardFoundation.test.ts CockpitDashboardShell.test.ts
```

Result:

```text
3 passed
35 passed
```

## Next checkpoint

Manual browser acceptance for `/x/cockpit`.
