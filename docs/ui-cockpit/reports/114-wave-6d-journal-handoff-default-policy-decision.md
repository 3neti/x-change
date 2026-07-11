# Cockpit Mutation Wave 6D — Journal Handoff Default Policy Decision

Status: Scaffolded / Decision recorded

Date: 2026-07-11

## Scope

This checkpoint is part of Cockpit Mutation Wave 6 — Production Hardening Plan.

Purpose:

```text
Decide that journal handoff must remain opt-in until idempotency, authorization, and failure semantics are hardened.
```

This is a documentation and guard-test checkpoint only. It does not change source behavior, Cockpit UI code, host-published assets, local `.env`, routes, controllers, APIs, migrations, models, repositories, recorders, durable activity defaults, journal writes, action execution, feedback delivery, provider calls, direct wallet mutation, voucher execution behavior, campaign mutation, or money movement behavior.

## Decision / Baseline

Key requirements recorded for this slice:

- journal handoff opt-in;
- idempotency key policy;
- failure semantics;
- no default journal writes;
- production default remains disabled;

## UI Effect

```text
No UI change; journal remains not_wired by default.
```

## Production Default Position

```text
Durable activity recording remains disabled by default.
```

## Boundary Confirmation

This checkpoint does not add runtime behavior, UI behavior, write-side journal behavior, action execution, feedback delivery, provider calls, direct wallet mutation, campaign mutation, raw payload exposure, or money movement behavior changes.

## Next Recommended Checkpoint

```text
6E — Action / Feedback Handoff Default Policy Decision
```
