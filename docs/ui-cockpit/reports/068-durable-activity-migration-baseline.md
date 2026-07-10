# Cockpit Mutation Wave 3F — Durable Activity Migration Baseline

Status: Implemented
Date: 2026-07-10

## Purpose

Create the package-owned database schema for future durable operator issuance activity storage.

This slice adds only the migration and schema tests. It does not introduce an Eloquent model, persistent repository, database write path, UI change, journal write, action execution, feedback delivery, provider call, wallet access, voucher execution change, or money movement.

## Added Migration

```text
2026_07_10_000400_create_x_change_cockpit_operator_issuance_activities_table.php
```

## Table

```text
x_change_cockpit_operator_issuance_activities
```

## Columns

The migration creates the durable activity columns approved in Wave 3E:

- `id`
- `activity_id`
- `schema`
- `actor_id`
- `actor_label`
- `source`
- `subject_type`
- `subject_reference`
- `status`
- `severity`
- `occurred_at`
- `idempotency_key_hash`
- `correlation_id`
- `causation_id`
- `summary`
- `safe_context`
- `redaction_flags`
- `journal_handoff_status`
- `action_handoff_status`
- `feedback_handoff_status`
- `retention_until`
- `metadata`
- `created_at`
- `updated_at`

## Indexes

The migration creates:

```text
index_activity_id_unique
index_operator_occurred_at
index_subject_reference
index_correlation_id
index_retention_until
```

## Boundary

The table is owned by the x-change package.

It exists to support future Cockpit operator issuance activity persistence behind:

```text
CockpitOperatorIssuanceActivityRepositoryContract
```

The current default repository binding remains:

```text
CockpitOperatorIssuanceActivityRepositoryContract
    → NullCockpitOperatorIssuanceActivityRepository
```

## Explicit Non-Authorization

This slice did not add:

- Eloquent models
- database repositories
- database writes
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

No Eloquent model was introduced.

No database repository was introduced.

No database writes were introduced.

## UI Impact

No UI was changed in this slice.

The Cockpit dashboard remains visually the same as Wave 2L / Wave 3F.

## Verification

Focused red baseline:

```text
2 failed, 2 assertions
```

Focused passing result:

```text
2 passed, 7 assertions
```

## Next Recommended Slice

Cockpit Mutation Wave 3G — Durable Activity Model Baseline.

Recommended scope:

- add the Eloquent model for the durable activity table
- add casts and guarded/fillable behavior
- prove no raw payload fields are model attributes
- do not add database write repository behavior yet
- preserve the current Cockpit UI
