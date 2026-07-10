# Cockpit Mutation Wave 3B — Durable Activity DTO and Repository Contract

Status: Implemented
Date: 2026-07-10

## Purpose

Introduce the first code-level durable activity boundary without adding persistence.

This slice creates:

- a durable operator issuance activity record DTO
- a repository contract
- a null non-persistent repository implementation
- a package service-provider binding

## Added Contracts and DTOs

```text
CockpitOperatorIssuanceActivityRecordData
CockpitOperatorIssuanceActivityRepositoryContract
NullCockpitOperatorIssuanceActivityRepository
```

## Record DTO Boundary

`CockpitOperatorIssuanceActivityRecordData` carries only operator-safe durable activity facts:

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

It does not include raw payload fields such as:

- `raw_payload`
- `provider_payload`
- `wallet`
- `balance`
- `account_number`
- `recipient_secret`
- `otp`
- `funding_source`

## Repository Contract Boundary

`CockpitOperatorIssuanceActivityRepositoryContract` defines:

```php
record(CockpitOperatorIssuanceActivityRecordData $record): CockpitOperatorIssuanceActivityRecordData
findByActivityId(string $activityId): ?CockpitOperatorIssuanceActivityRecordData
recent(CockpitReadModelQueryData $query, int $limit = 25): array
```

The contract is storage-agnostic and does not require database persistence.

## Null Implementation

`NullCockpitOperatorIssuanceActivityRepository` is the default binding.

Behavior:

- `record()` returns the supplied record
- `findByActivityId()` returns `null`
- `recent()` returns an empty array

This preserves the current no-persistence behavior while allowing future storage adapters to be introduced behind the contract.

## Service Provider Binding

The package service provider binds:

```text
CockpitOperatorIssuanceActivityRepositoryContract
    → NullCockpitOperatorIssuanceActivityRepository
```

## Explicit Non-Authorization

This slice did not add:

- migrations
- Eloquent models
- database writes
- persistent repositories
- queue jobs
- scheduled purges
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

The Cockpit dashboard remains visually the same as Wave 2L.

## Verification

Focused red baseline:

```text
3 failed
```

Focused passing result:

```text
3 passed, 14 assertions
```

## Next Recommended Slice

Cockpit Mutation Wave 3C — In-Memory Durable Activity Repository Baseline.

Recommended scope:

- in-memory repository implementation
- capped recent query behavior
- no migrations
- no Eloquent model
- no database writes
- no UI changes
