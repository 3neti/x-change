# Wave 5L — Cockpit Mutation Wave 5 Closure Report

Status: Closed pending manual UI review

Date: 2026-07-11

## Scope

This checkpoint closes Cockpit Mutation Wave 5.

Wave 5 covered real operator activity rollout cleanup, Brick\Math monetary warning resolution, synthetic fixture cleanup, local durable activity opt-in closure, and production readiness decision.

## Completed Checkpoints

| Checkpoint | Status | UI effect |
|---|---|---|
| Cash/Voucher Upstream BrickMath Fix Execution | Completed upstream | None |
| Retry Wave 5H — x-change Characterization Flip | Completed | None |
| Wave 5I — Real Activity Fixture Cleanup Decision / Execution | Completed locally | Yes: synthetic `PC-LOCAL-DIAGNOSTIC` card should disappear |
| Wave 5J — Durable Activity Local Opt-In Closure | Closed locally | Yes: real `MCPC` activity remains visible because local opt-in stays enabled |
| Wave 5K — Real Activity Production Readiness Decision | Completed | None |
| Wave 5L — Cockpit Mutation Wave 5 Closure Report | Completed | None |

## Commits

Cash package:

```text
198ff60 cash: normalize monetary floats before BrickMoney
```

Voucher package:

```text
7a6889e voucher: verify cash persistence avoids BrickMath floats
```

x-change package:

```text
b4956d3 cockpit: record upstream brickmath fix execution
73c21da cockpit: verify brickmath monetary warning is resolved
2230ab6 cockpit: clean up local diagnostic fixture
d704c40 cockpit: close durable activity local opt in
e98ac94 cockpit: defer durable activity production default
```

## Test Evidence

Cash package:

```text
Focused: 2 passed, 7 assertions
Full suite: 95 passed, 194 assertions
```

Voucher package:

```text
Focused: 1 passed, 8 assertions
Execution/regression group: 29 passed, 96 assertions
Full suite: 387 passed, 28 skipped, 1159 assertions
```

x-change focused evidence:

```text
GeneratePayCode BrickMath flip: passed
Wave 5 guard tests: passed
```

## Local Data State

Synthetic fixture:

```text
PC-LOCAL-DIAGNOSTIC removed.
fixture_count: 0
```

Real activity:

```text
MCPC remains.
MCPC count: 1
```

Local durable activity configuration:

```text
Repository: DatabaseCockpitOperatorIssuanceActivityRepository
Recorder: DatabaseCockpitOperatorIssuanceActivityRecorder
```

Production default:

```text
Durable activity recording remains disabled by default.
```

## Expected Cockpit UI State

Route:

```text
http://x-change-sandbox.test/x/cockpit
```

Expected Operator Issuance Activity panel:

- should show real `MCPC` Quick Generate activity;
- should not show synthetic `PC-LOCAL-DIAGNOSTIC`;
- should still show journal/action/feedback as not wired for the real activity;
- should not expose raw payloads, wallet payloads, provider payloads, secrets, tokens, OTPs, or recipient secrets;
- should not show retry controls or new mutation controls.

## Remaining Production Risks

Durable activity production default enablement remains deferred pending:

1. Operator authorization and tenant scoping.
2. Retention and purge schedule.
3. Production observability for recorder failures.
4. Journal handoff default policy.
5. Action handoff default policy.
6. Feedback handoff default policy.
7. PII classification review.
8. Production runbook for disabling durable activity recording.
9. Cockpit search/filter policy.
10. High-volume projection strategy.

## Boundary Confirmation

Wave 5 did not add:

- production default durable activity recording;
- new Cockpit mutation controls beyond the existing Quick Generate path;
- journal writes by default;
- action execution;
- feedback delivery;
- provider calls outside existing issuance;
- direct wallet mutation outside existing issuance;
- voucher execution behavior changes;
- campaign mutation;
- lifecycle truth ownership;
- raw payload exposure;
- money movement behavior changes.

## Recommended Manual UI Review

Open:

```text
http://x-change-sandbox.test/x/cockpit
```

Verify:

1. `Operator Issuance Activity` shows `Pay Code MCPC issued`.
2. `PC-LOCAL-DIAGNOSTIC` is absent.
3. Journal/action/feedback remain not wired for the real activity.
4. No raw payloads or secrets are visible.
5. No retry controls or new mutation controls are visible.

## Next Recommended Work

After manual UI review:

```text
Cockpit Mutation Wave 6 — Production Hardening Plan
```

Recommended first slice:

```text
Wave 6A — Durable Activity Authorization / Tenant Scope Decision
```

No further Wave 5 implementation checkpoints remain.
