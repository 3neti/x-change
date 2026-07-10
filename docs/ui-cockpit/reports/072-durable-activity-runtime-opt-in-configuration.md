# Cockpit Mutation Wave 3J — Durable Activity Runtime Opt-In Configuration

Status: Implemented

Date: 2026-07-10

## Scope

This checkpoint adds explicit configuration seams that let a host opt into database-backed operator issuance activity storage.

The default runtime remains non-persistent.

## Implemented

- Added `x-change.cockpit.operator_issuance_activity.repository`.
- Added `x-change.cockpit.operator_issuance_activity.recorder`.
- Added documented available database service classes under:
  - `x-change.cockpit.operator_issuance_activity.available_repositories.database`
  - `x-change.cockpit.operator_issuance_activity.available_recorders.database`
- Updated `XChangeServiceProvider` so the repository and recorder contracts resolve from config at runtime.
- Kept null repository and null recorder as the default bindings when config is missing, empty, or null.
- Proved Quick Generate writes no durable activity by default.
- Proved Quick Generate persists durable activity only when the database repository and recorder are explicitly configured.

## Runtime Boundary

Default:

```text
CockpitOperatorIssuanceActivityRepositoryContract
    → NullCockpitOperatorIssuanceActivityRepository

CockpitOperatorIssuanceActivityRecorderContract
    → NullCockpitOperatorIssuanceActivityRecorder
```

Opt-in:

```text
x-change.cockpit.operator_issuance_activity.repository
    → DatabaseCockpitOperatorIssuanceActivityRepository

x-change.cockpit.operator_issuance_activity.recorder
    → DatabaseCockpitOperatorIssuanceActivityRecorder
```

## Non-Goals

- No Cockpit UI was changed.
- No automatic production persistence was enabled.
- No journal writes were added.
- No x-action execution was added.
- No x-feedback delivery was added.
- No queue jobs, retries, or async dispatch were added.
- No provider calls were added beyond the existing Quick Generate issuance path.
- No wallet access or money movement behavior was changed.
- No voucher execution behavior was changed.
- No raw payload persistence was added.

## Tests

- Red baseline:
  - `php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitOperatorIssuanceActivityRuntimeOptInConfigurationTest.php`
  - Result: `2 failed, 1 passed, 11 assertions`
- Focused implementation:
  - `php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitOperatorIssuanceActivityRuntimeOptInConfigurationTest.php`
  - Result: `3 passed, 13 assertions`

## Next Recommended Checkpoint

Cockpit Mutation Wave 3K — Durable Activity Read Model Adapter

Recommended scope:

- add a read model provider/decorator that reads durable operator issuance activity records from the configured repository
- keep dashboard presentation read-only
- show database-backed activity only when the repository is explicitly configured
- preserve null/not-wired presentation when persistence is disabled
- do not add new mutation controls
