# Cockpit Mutation Wave 6F — PII Classification / Redaction Hardening Review

Status: Scaffolded / Decision recorded

Date: 2026-07-11

## Scope

This checkpoint is part of Cockpit Mutation Wave 6 — Production Hardening Plan.

Purpose:

```text
Review durable activity fields and keep raw payload/secret exposure prohibited before production default enablement.
```

This is a documentation and guard-test checkpoint only. It does not change source behavior, Cockpit UI code, host-published assets, local `.env`, routes, controllers, APIs, migrations, models, repositories, recorders, durable activity defaults, journal writes, action execution, feedback delivery, provider calls, direct wallet mutation, voucher execution behavior, campaign mutation, or money movement behavior.

## Decision / Baseline

Key requirements recorded for this slice:

- PII classification;
- raw payload prohibited;
- secret/token/OTP prohibited;
- redaction flags;
- production default remains disabled;

## UI Effect

```text
No direct UI change; current redacted UI remains the required behavior.
```

## Production Default Position

```text
Durable activity recording remains disabled by default.
```

## Boundary Confirmation

This checkpoint does not add runtime behavior, UI behavior, write-side journal behavior, action execution, feedback delivery, provider calls, direct wallet mutation, campaign mutation, raw payload exposure, or money movement behavior changes.

## Next Recommended Checkpoint

```text
6G — Production Disable / Rollback Runbook
```
