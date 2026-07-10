# Cockpit Mutation Wave 3D — Activity Redaction and Retention Policy Contracts

Status: Implemented
Date: 2026-07-10

## Purpose

Define the policy seams that protect future durable operator issuance activity records before database-backed storage is introduced.

This slice establishes redaction and retention contracts without changing Cockpit UI, persistence, mutation behavior, journal writes, action execution, feedback delivery, provider calls, wallet access, voucher execution, or money movement.

## Added Contracts

```text
CockpitOperatorIssuanceActivityRedactionPolicyContract
CockpitOperatorIssuanceActivityRetentionPolicyContract
```

## Added Defaults

```text
DefaultCockpitOperatorIssuanceActivityRedactionPolicy
DefaultCockpitOperatorIssuanceActivityRetentionPolicy
```

## Behavior

The default redaction policy:

- accepts a `CockpitOperatorIssuanceActivityRecordData`
- redacts sensitive activity context and metadata keys
- preserves safe operator display fields such as amount, currency, activity ID, subject reference, and summary
- normalizes redaction flags to prove raw payloads, provider payloads, wallet data, and recipient secrets are not exposed
- returns a new immutable activity record DTO

The default retention policy:

- preserves an explicit `retention_until` value when present
- derives retention from `occurred_at` plus the configured retention window when no explicit value exists
- defaults to 30 days
- treats records as retainable only when they have an activity ID and unsafe exposure flags are false

## Service Provider Bindings

```text
CockpitOperatorIssuanceActivityRedactionPolicyContract
    → DefaultCockpitOperatorIssuanceActivityRedactionPolicy

CockpitOperatorIssuanceActivityRetentionPolicyContract
    → DefaultCockpitOperatorIssuanceActivityRetentionPolicy
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

The Cockpit dashboard remains visually the same as Wave 2L / Wave 3C.

No migrations were introduced.

No database writes were introduced.

## Verification

Focused red baseline:

```text
4 failed, 0 assertions
```

Focused passing result:

```text
4 passed, 13 assertions
```

## Next Recommended Slice

Cockpit Mutation Wave 3E — Database Migration Decision Point.

Recommended scope:

- decide whether durable activity storage should use a package migration now
- document schema, indexes, and redaction/retention enforcement points before creating a table
- keep mutation behavior unchanged until explicit persistence implementation is approved
