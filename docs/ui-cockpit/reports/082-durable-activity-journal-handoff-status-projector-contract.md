# Cockpit Mutation Wave 4F — Durable Activity Journal Handoff Status Projector Contract

Status: Implemented

Date: 2026-07-10

## Scope

This checkpoint adds the package-local contract for projecting a journal handoff result into durable activity status semantics.

It does not persist that status yet.

## Implemented

- Added `CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract`.
- Added `CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData`.
- Added `NullCockpitOperatorIssuanceActivityJournalHandoffStatusProjector`.
- Bound the projector contract to the null projector by default.

## Runtime Behavior

The null projector accepts `CockpitOperatorIssuanceActivityJournalHandoffResultData` and returns a projection result containing:

- activity ID;
- correlation ID;
- journal handoff status;
- journal entry ID;
- safe handoff metadata;
- explicit `persists_status: false`.

## Boundary

The null projector does not mutate durable activity rows.

This keeps the status projection boundary separate from the future persistence adapter.

## Non-Goals

- No durable activity row mutation.
- No repository update method.
- No migration change.
- No queue job.
- No retry orchestration.
- No x-journal call.
- No journal write.
- No action execution.
- No feedback delivery.
- No provider call.
- No wallet access.
- No voucher execution change.
- No lifecycle truth ownership.
- No raw payload exposure.
- No UI changes.
- No money movement.

## Tests

- `tests/Unit/Cockpit/CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorTest.php`
- `tests/Feature/Cockpit/CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorRuntimeTest.php`
- `tests/Unit/Architecture/CockpitDurableActivityJournalHandoffStatusProjectorContractTest.php`

## Next checkpoint

Cockpit Mutation Wave 4G — Durable Activity Journal Handoff Status Persistence Adapter.

Recommended scope:

- add an opt-in persistence adapter behind `CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract`;
- update only `journal_handoff_status` and safe handoff metadata;
- preserve action and feedback handoff statuses;
- no-op when the durable activity row is missing;
- keep status persistence non-blocking.
