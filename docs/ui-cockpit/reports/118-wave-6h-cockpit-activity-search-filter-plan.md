# Cockpit Mutation Wave 6H — Cockpit Activity Search / Filter Plan

Status: Scaffolded / Decision recorded

Date: 2026-07-11

## Scope

This checkpoint is part of Cockpit Mutation Wave 6 — Production Hardening Plan.

Purpose:

```text
Plan future read-only search and filtering for durable operator activity history.
```

This is a documentation and guard-test checkpoint only. It does not change source behavior, Cockpit UI code, host-published assets, local `.env`, routes, controllers, APIs, migrations, models, repositories, recorders, durable activity defaults, journal writes, action execution, feedback delivery, provider calls, direct wallet mutation, voucher execution behavior, campaign mutation, or money movement behavior.

## Decision / Baseline

Key requirements recorded for this slice:

- search plan;
- filter plan;
- read-only query contract;
- pagination;
- redaction-aware results;

## UI Effect

```text
Future UI candidate, but this slice adds no UI.
```

## Production Default Position

```text
Durable activity recording remains disabled by default.
```

## Boundary Confirmation

This checkpoint does not add runtime behavior, UI behavior, write-side journal behavior, action execution, feedback delivery, provider calls, direct wallet mutation, campaign mutation, raw payload exposure, or money movement behavior changes.

## Next Recommended Checkpoint

```text
6I — High-Volume Projection / Queue Decision
```
