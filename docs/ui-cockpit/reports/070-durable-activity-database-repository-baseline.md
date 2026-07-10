# Cockpit Mutation Wave 3H — Durable Activity Database Repository Baseline

Status: Implemented
Date: 2026-07-10

## Purpose

Add an opt-in database-backed repository implementation for durable operator issuance activity records.

This slice proves persistence behavior behind the existing repository contract while keeping the default runtime binding on the null repository. Cockpit does not persist operator activity by default yet.

## Added Repository

```text
DatabaseCockpitOperatorIssuanceActivityRepository
```

The repository implements:

```text
CockpitOperatorIssuanceActivityRepositoryContract
```

## Policy Enforcement

Before persistence, the repository applies:

```text
CockpitOperatorIssuanceActivityRedactionPolicyContract
CockpitOperatorIssuanceActivityRetentionPolicyContract
```

The repository:

- redacts unsafe `safe_context` and `metadata`
- normalizes redaction flags
- derives or preserves `retention_until`
- refuses to persist non-retainable records
- upserts by durable `activity_id`
- retrieves by `activity_id`
- returns recent records newest-first with query filters

## Binding Decision

Default binding remains null:

```text
CockpitOperatorIssuanceActivityRepositoryContract
    → NullCockpitOperatorIssuanceActivityRepository
```

The database repository is opt-in for tests or future host/package configuration.

No provider binding changed.

## Explicit Non-Authorization

This slice did not add:

- default persistent repository binding
- recorder-to-database wiring
- automatic Cockpit persistence
- queue jobs
- scheduled purge jobs
- journal writes
- action execution
- feedback delivery
- provider calls
- wallet access
- voucher execution changes
- lifecycle truth ownership
- raw payload persistence
- UI changes
- mutation controls
- money movement

## UI Impact

No UI was changed in this slice.

The Cockpit dashboard remains visually the same as Wave 2L / Wave 3H.

## Verification

Focused red baseline:

```text
4 failed, 1 passed, 1 assertion
```

Focused passing result:

```text
5 passed, 15 assertions
```

## Next Recommended Slice

Cockpit Mutation Wave 3I — Durable Activity Recorder Opt-In Boundary.

Recommended scope:

- add an opt-in recorder implementation that writes operator activity through `CockpitOperatorIssuanceActivityRepositoryContract`
- keep the default recorder binding null unless explicitly configured
- keep Quick Generate behavior unchanged by default
- do not change Cockpit UI
