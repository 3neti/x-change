# Dashboard Activity Rationalization Slice 1 — Rename Panels and Copy

Date: 2026-07-16

## Result

Renamed dashboard activity concepts so operators can distinguish generated Pay Code activity from execution evidence.

## What changed

- `Operator Issuance Activity` became `Issuance Activity`.
- `Quick Generate evidence` became `Generated Pay Codes`.
- `Recent Activity` became `Execution Activity`.
- `Redaction-aware activity placeholder` became `Execution evidence`.
- `Lifecycle visibility placeholder` became `Redemption status`.
- `Attention queue placeholder` became `Risk signals`.

## Boundary

This slice is copy and presentation only.

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
32 passed
```

## Next checkpoint

Dashboard Activity Rationalization Slice 2 — Reorder dashboard sections.
