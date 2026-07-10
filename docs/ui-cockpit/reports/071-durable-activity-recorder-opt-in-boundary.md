# Cockpit Mutation Wave 3I — Durable Activity Recorder Opt-In Boundary

Status: Implemented
Date: 2026-07-10

## Purpose

Add an opt-in recorder implementation that can convert Quick Generate operator activity items into durable activity records through the repository contract.

This slice makes durable activity recording possible when explicitly wired, but it keeps the default runtime recorder null. Quick Generate behavior is unchanged by default.

## Added Recorder

```text
DatabaseCockpitOperatorIssuanceActivityRecorder
```

The recorder implements:

```text
CockpitOperatorIssuanceActivityRecorderContract
```

and writes through:

```text
CockpitOperatorIssuanceActivityRepositoryContract
```

## Behavior

The opt-in recorder:

- converts `CockpitOperatorIssuanceActivityItemData` into `CockpitOperatorIssuanceActivityRecordData`
- uses the item ID as the durable `activity_id`
- maps the Pay Code to `subject_reference`
- maps operator ID to `actor_id`
- hashes the raw idempotency key before handoff
- stores only operator-safe context values
- passes metadata through the repository redaction boundary

## Binding Decision

Default recorder binding remains null:

```text
CockpitOperatorIssuanceActivityRecorderContract
    → NullCockpitOperatorIssuanceActivityRecorder
```

No provider binding changed.

The database recorder is opt-in for tests or future host/package configuration.

## Explicit Non-Authorization

This slice did not add:

- default persistent recorder binding
- default persistent repository binding
- automatic Cockpit persistence
- config-driven runtime opt-in
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

The Cockpit dashboard remains visually the same as Wave 2L / Wave 3I.

## Verification

Focused red baseline:

```text
1 failed, 1 passed, 1 assertion
```

Focused passing result:

```text
2 passed, 8 assertions
```

## Next Recommended Slice

Cockpit Mutation Wave 3J — Durable Activity Runtime Opt-In Configuration.

Recommended scope:

- add config-driven opt-in seams for database repository and recorder
- keep defaults null/non-persistent
- prove Quick Generate can persist activity only when explicitly configured
- do not change Cockpit UI
