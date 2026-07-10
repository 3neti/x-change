# Cockpit Mutation Wave 3G — Durable Activity Model Baseline

Status: Implemented
Date: 2026-07-10

## Purpose

Add the Eloquent model for the durable operator issuance activity table without introducing repository writes.

This slice gives future repository work a typed model boundary while preserving the existing null repository default and current Cockpit UI.

## Added Model

```text
CockpitOperatorIssuanceActivity
```

## Table Mapping

```text
x_change_cockpit_operator_issuance_activities
```

## Fillable Attributes

The model allows only operator-safe durable activity attributes:

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

The model does not expose raw payload, provider payload, wallet, balance, account number, recipient secret, OTP, funding source, or raw idempotency key attributes as fillable model attributes.

## Casts

The model casts:

```text
safe_context      → array
redaction_flags   → array
metadata          → array
occurred_at       → datetime
retention_until   → datetime
```

## Boundary

The model is storage shape only.

The default repository binding remains:

```text
CockpitOperatorIssuanceActivityRepositoryContract
    → NullCockpitOperatorIssuanceActivityRepository
```

No repository binding changed.

## Explicit Non-Authorization

This slice did not add:

- database repositories
- repository binding changes
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

No database repository was introduced.

No repository binding changed.

No database writes were introduced.

## UI Impact

No UI was changed in this slice.

The Cockpit dashboard remains visually the same as Wave 2L / Wave 3G.

## Verification

Focused red baseline:

```text
3 failed, 0 assertions
```

Focused passing result:

```text
3 passed, 8 assertions
```

## Next Recommended Slice

Cockpit Mutation Wave 3H — Durable Activity Database Repository Baseline.

Recommended scope:

- add a database-backed repository implementation behind `CockpitOperatorIssuanceActivityRepositoryContract`
- apply the redaction and retention policies before persistence
- keep the default binding on the null repository unless an explicit host/test binding opts in
- do not change Cockpit UI
