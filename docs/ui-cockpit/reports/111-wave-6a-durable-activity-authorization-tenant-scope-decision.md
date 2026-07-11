# Cockpit Mutation Wave 6A — Durable Activity Authorization / Tenant Scope Decision

Status: Scaffolded / Decision recorded

Date: 2026-07-11

## Scope

This checkpoint is part of Cockpit Mutation Wave 6 — Production Hardening Plan.

Purpose:

```text
Decide that production durable activity reads require explicit operator authorization and tenant scoping before default enablement.
```

This is a documentation and guard-test checkpoint only. It does not change source behavior, Cockpit UI code, host-published assets, local `.env`, routes, controllers, APIs, migrations, models, repositories, recorders, durable activity defaults, journal writes, action execution, feedback delivery, provider calls, direct wallet mutation, voucher execution behavior, campaign mutation, or money movement behavior.

## Decision / Baseline

Key requirements recorded for this slice:

- operator authorization;
- tenant scoping;
- policy/gate boundary;
- no cross-tenant reads;
- production default remains disabled;

## UI Effect

```text
No UI change; future UI may filter activity by authorized tenant/operator scope.
```

## Production Default Position

```text
Durable activity recording remains disabled by default.
```

## Boundary Confirmation

This checkpoint does not add runtime behavior, UI behavior, write-side journal behavior, action execution, feedback delivery, provider calls, direct wallet mutation, campaign mutation, raw payload exposure, or money movement behavior changes.

## Next Recommended Checkpoint

```text
6B — Durable Activity Retention / Purge Policy Decision
```
