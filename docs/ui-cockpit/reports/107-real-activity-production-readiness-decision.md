# Wave 5K — Real Activity Production Readiness Decision

Status: Not ready for production default enablement

Date: 2026-07-11

## Scope

This checkpoint decides whether durable Cockpit operator issuance activity recording should become production-enabled by default.

This is a decision-only checkpoint. It does not change source behavior, Cockpit UI code, host-published assets, local `.env`, routes, controllers, APIs, migrations, models, repositories, recorders, durable activity defaults, journal writes, action execution, feedback delivery, provider calls, direct wallet mutation, voucher execution behavior, campaign mutation, or money movement behavior.

## Decision

Decision:

```text
Do not enable durable Cockpit operator issuance activity recording by default in production yet.
```

Rationale:

- Local proof is complete.
- Real `MCPC` activity is visible locally through opt-in configuration.
- Synthetic fixture cleanup is complete.
- Brick\Math warning is resolved through the cash/voucher/x-change test path.
- Production enablement still needs operational policy decisions that are broader than the local Cockpit proof.

## Production Blockers

Durable activity recording should remain opt-in until these are decided:

1. Operator authorization and tenant scoping for durable activity reads.
2. Retention and purge schedule for durable activity rows.
3. Production observability for recorder failures.
4. Journal handoff default policy.
5. Action handoff default policy.
6. Feedback handoff default policy.
7. PII classification review for activity context and metadata.
8. Production runbook for disabling durable activity recording.
9. Cockpit search/filter policy for historical operator activity.
10. Confirmation that high-volume Quick Generate usage does not require queueing or async projection.

## Current Safe Production Position

Current production-safe position:

```text
Durable operator issuance activity remains package-supported but disabled by default.
```

Local/manual testing may keep the database repository and recorder enabled through uncommitted local configuration.

## UI Effect

Expected UI effect:

```text
None.
```

The local UI remains governed by local `.env` configuration. Since this checkpoint does not change local config, the real `MCPC` activity should remain visible locally.

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
Wave 5L — Cockpit Mutation Wave 5 Closure Report
```

Expected UI effect:

```text
None.
```
