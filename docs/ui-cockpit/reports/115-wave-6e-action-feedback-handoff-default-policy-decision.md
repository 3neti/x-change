# Cockpit Mutation Wave 6E — Action / Feedback Handoff Default Policy Decision

Status: Scaffolded / Decision recorded

Date: 2026-07-11

## Scope

This checkpoint is part of Cockpit Mutation Wave 6 — Production Hardening Plan.

Purpose:

```text
Decide that action and feedback handoffs remain opt-in and non-default until execution/delivery semantics are explicitly authorized.
```

This is a documentation and guard-test checkpoint only. It does not change source behavior, Cockpit UI code, host-published assets, local `.env`, routes, controllers, APIs, migrations, models, repositories, recorders, durable activity defaults, journal writes, action execution, feedback delivery, provider calls, direct wallet mutation, voucher execution behavior, campaign mutation, or money movement behavior.

## Decision / Baseline

Key requirements recorded for this slice:

- action handoff opt-in;
- feedback handoff opt-in;
- no action execution by default;
- no feedback delivery by default;
- production default remains disabled;

## UI Effect

```text
No UI change; action and feedback remain not_wired by default.
```

## Production Default Position

```text
Durable activity recording remains disabled by default.
```

## Boundary Confirmation

This checkpoint does not add runtime behavior, UI behavior, write-side journal behavior, action execution, feedback delivery, provider calls, direct wallet mutation, campaign mutation, raw payload exposure, or money movement behavior changes.

## Next Recommended Checkpoint

```text
6F — PII Classification / Redaction Hardening Review
```
