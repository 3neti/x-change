# Wave 5J — Durable Activity Local Opt-In Closure

Status: Closed locally

Date: 2026-07-11

## Scope

This checkpoint closes the local durable activity opt-in decision for continued manual Cockpit UI review.

No source behavior, Cockpit UI code, host-published assets, routes, controllers, APIs, migrations, models, repositories, recorders, journal writes, action execution, feedback delivery, provider calls, direct wallet mutation, voucher execution behavior, campaign mutation, or money movement behavior was changed.

## Decision

Decision:

```text
Keep local durable activity repository and recorder enabled.
```

Rationale:

- The synthetic fixture has been removed.
- The real `MCPC` durable activity remains as the current visual verification row.
- Keeping local opt-in enabled lets the Cockpit Operator Issuance Activity panel show real durable activity during the next UI review.
- Production defaults remain unchanged and durable activity recording is not production-enabled by default.

## Local Configuration Verified

Repository:

```text
LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository
```

Recorder:

```text
LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder
```

Real local activity verification:

```text
MCPC count: 1
```

## Expected UI Effect

The Cockpit Operator Issuance Activity panel should continue showing the real `MCPC` Quick Generate activity.

The synthetic `PC-LOCAL-DIAGNOSTIC` card should remain absent.

If local durable activity configuration is later disabled, the panel may return to an empty/not-wired state even though source code remains unchanged.

## Boundary Confirmation

This checkpoint did not add:

- source behavior changes;
- Cockpit UI code changes;
- committed `.env` changes;
- database writes;
- database deletes;
- host-published asset changes;
- route changes;
- controller changes;
- API changes;
- migrations;
- model changes;
- repository changes;
- recorder changes;
- production default durable activity recording;
- new Quick Generate semantics;
- voucher execution changes;
- journal writes;
- action execution;
- feedback delivery;
- provider calls;
- direct wallet access changes;
- direct wallet mutation changes;
- lifecycle truth ownership;
- raw payload exposure;
- retry controls;
- new mutation controls;
- campaign mutation;
- money movement behavior changes.

## Next Recommended Checkpoint

```text
Wave 5K — Real Activity Production Readiness Decision
```

Expected UI effect:

```text
None. This should be a production readiness decision only.
```
