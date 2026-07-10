# Cockpit Mutation Wave 3E — Database Migration Decision Point

Status: Implemented
Date: 2026-07-10

## Purpose

Close the database migration decision before introducing durable operator issuance activity storage.

This is a decision-point slice only. It documents the target storage shape, indexes, redaction enforcement, retention enforcement, and package boundary before any migration, Eloquent model, persistent repository, or database write path is introduced.

## Decision

Decision: Proceed with a package-owned durable activity table in the next implementation slice.

The table should live in the x-change package because Cockpit operator issuance activity is an x-change product/orchestration concern:

- it describes Cockpit operator-visible Quick Generate issuance activity
- it records package-owned mutation handoff facts
- it must remain independent of x-journal, x-action, x-feedback, and x-campaign storage
- it should not require host-app migrations beyond normal package migration loading

## Proposed Table

```text
x_change_cockpit_operator_issuance_activities
```

## Proposed Columns

| Column | Purpose |
|---|---|
| `id` | Internal database primary key |
| `activity_id` | Durable operator activity identifier |
| `schema` | Activity record schema version |
| `actor_id` | Optional operator identifier |
| `actor_label` | Optional operator-safe display label |
| `source` | Source surface, usually `cockpit.quick-generate` |
| `subject_type` | Activity subject type, initially `pay_code` |
| `subject_reference` | Operator-safe Pay Code or subject reference |
| `status` | Activity status, such as `recorded`, `issued`, or `failed` |
| `severity` | Operator display severity |
| `occurred_at` | Activity occurrence timestamp |
| `idempotency_key_hash` | Hash only; never raw idempotency key |
| `correlation_id` | Correlation identifier for cross-layer read models |
| `causation_id` | Causation identifier for handoff chains |
| `summary` | Operator-safe summary |
| `safe_context` | Redacted operator-safe JSON context |
| `redaction_flags` | Explicit redaction proof flags |
| `journal_handoff_status` | Journal handoff state |
| `action_handoff_status` | Action handoff state |
| `feedback_handoff_status` | Feedback handoff state |
| `retention_until` | Retention deadline for purge/review policy |
| `metadata` | Redacted technical metadata |
| `created_at` / `updated_at` | Storage timestamps |

## Proposed Indexes

```text
index_activity_id_unique
index_operator_occurred_at
index_subject_reference
index_correlation_id
index_retention_until
```

The unique `activity_id` index is required for idempotent activity recording and duplicate prevention.

The operator/timestamp, subject reference, and correlation indexes support Cockpit read-model retrieval without broad table scans.

The retention index supports future purge/review jobs without requiring full-table scans.

## Redaction Enforcement Point

Future persistent repositories must call:

```text
CockpitOperatorIssuanceActivityRedactionPolicyContract
```

before writing `safe_context`, `metadata`, or `redaction_flags`.

Raw payloads, provider payloads, wallet data, recipient secrets, OTP values, raw idempotency keys, account numbers, authorization headers, tokens, and provider credentials must not be stored in this table.

## Retention Enforcement Point

Future persistent repositories must call:

```text
CockpitOperatorIssuanceActivityRetentionPolicyContract
```

before writing `retention_until` or accepting a record as retainable.

Records with missing `activity_id` values or unsafe exposure flags must not be persisted by the future database repository.

## Repository Boundary

The existing repository contract remains the correct write/read seam:

```text
CockpitOperatorIssuanceActivityRepositoryContract
```

The next implementation slice may introduce a database repository behind that contract. It should not change Quick Generate mutation behavior beyond swapping the explicit storage adapter.

## Explicit Non-Authorization

This slice did not add:

- migration files
- Eloquent models
- database writes
- persistent repositories
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

No migration file was created in this slice.

No Eloquent model was introduced.

No database writes were introduced.

## UI Impact

No UI was changed in this slice.

The Cockpit dashboard remains visually the same as Wave 2L / Wave 3D.

## Verification

Focused red baseline:

```text
1 failed, 1 passed, 2 assertions
```

## Next Recommended Slice

Cockpit Mutation Wave 3F — Durable Activity Migration Baseline.

Recommended scope:

- add the package migration for `x_change_cockpit_operator_issuance_activities`
- add schema tests for columns and indexes
- do not add an Eloquent model yet unless the migration test requires it
- do not add database writes until the database repository slice
- preserve the current Cockpit UI
