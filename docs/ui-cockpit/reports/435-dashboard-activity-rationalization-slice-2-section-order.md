# Dashboard Activity Rationalization Slice 2 — Reorder Dashboard Sections

Date: 2026-07-16

## Result

Reordered `/x/cockpit` so activity concepts sit in the operator workflow order.

## New order

1. Settlement OS Operating Overview.
2. Operator Console.
3. Operator Focus.
4. Issuance Activity.
5. Execution Activity.
6. Integration Summary.
7. Liquidity Center.
8. Redemption status and Risk signals.
9. Campaign Cockpit Adoption.

## Boundary

This slice is layout-only.

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
33 passed
```

## Next checkpoint

Dashboard Activity Rationalization Slice 3 — Distinct activity concept tests.
