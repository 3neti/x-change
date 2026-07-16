# Dashboard Final Copy Polish — Replace Remaining Engineering Tokens

Date: 2026-07-16

## Result

Replaced remaining dashboard-facing engineering tokens with operator-friendly copy and normalized compact action/status pill sizing.

## What changed

- `not_wired` renders as `Not connected`.
- `not-loaded` renders as `No data loaded`.
- `read-model-ready` renders as `Ready for display`.
- `durable_summary_evidence_available` renders as `Summary evidence available`.
- `runtime_handoff_profile_only` renders as `Runtime profile only`.
- `campaign-mutations-not-authorized` renders as `Campaign changes disabled`.
- Raw campaign snake-case labels render as title-cased operator labels.
- Visible activity filter copy now says `Follow-up status` instead of `Handoff`.
- Dashboard action links and status pills use compact no-wrap sizing to avoid uneven badge height.

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
npm run test:frontend -- CockpitDashboardHydration.test.ts CockpitDashboardFoundation.test.ts CockpitDashboardShell.test.ts
```

Result:

```text
3 passed
35 passed
```

## Next checkpoint

Manual browser acceptance for `/x/cockpit`, then select the next page-specific productization target.
