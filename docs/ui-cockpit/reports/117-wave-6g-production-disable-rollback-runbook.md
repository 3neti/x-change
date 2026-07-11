# Cockpit Mutation Wave 6G — Production Disable / Rollback Runbook

Status: Scaffolded / Decision recorded

Date: 2026-07-11

## Scope

This checkpoint is part of Cockpit Mutation Wave 6 — Production Hardening Plan.

Purpose:

```text
Define the runbook for disabling durable activity recording safely in production.
```

This is a documentation and guard-test checkpoint only. It does not change source behavior, Cockpit UI code, host-published assets, local `.env`, routes, controllers, APIs, migrations, models, repositories, recorders, durable activity defaults, journal writes, action execution, feedback delivery, provider calls, direct wallet mutation, voucher execution behavior, campaign mutation, or money movement behavior.

## Decision / Baseline

Key requirements recorded for this slice:

- disable config;
- rollback steps;
- config cache clear;
- verification query;
- production default remains disabled;

## UI Effect

```text
No UI change unless config is disabled; then activity panel may return to empty/not-wired.
```

## Production Default Position

```text
Durable activity recording remains disabled by default.
```

## Boundary Confirmation

This checkpoint does not add runtime behavior, UI behavior, write-side journal behavior, action execution, feedback delivery, provider calls, direct wallet mutation, campaign mutation, raw payload exposure, or money movement behavior changes.

## Next Recommended Checkpoint

```text
6H — Cockpit Activity Search / Filter Plan
```
