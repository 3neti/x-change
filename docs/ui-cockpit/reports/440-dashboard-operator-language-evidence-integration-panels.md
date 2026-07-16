# Dashboard Operator Language Polish — Evidence and Integration Panels

Date: 2026-07-16

## Result

Polished dashboard evidence and connected-service language so the operator view reads less like package internals.

## What changed

- `Execution Activity` became `Settlement Activity`.
- `Execution evidence` became `Recent settlement events`.
- `Targets` became `Evidence sources`.
- `Integration Summary` became `Connected Services`.
- `Journal · Action · Feedback readiness` became `Audit, follow-up, and notification status`.
- `x-journal evidence source` became `Audit trail source`.
- `x-action continuation source` became `Follow-up action source`.
- `x-feedback delivery source` became `Notification source`.
- `Campaign Api Descriptors` renders as `Campaign API Descriptors`.

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
npm run test:frontend -- CockpitDashboardHydration.test.ts CockpitDashboardFoundation.test.ts CockpitDashboardShell.test.ts CockpitReadOnlyScenarioValidation.test.ts
```

Result:

```text
4 passed
38 passed
```

## Next checkpoint

Manual browser acceptance for `/x/cockpit`, then select the next page-specific productization target.
