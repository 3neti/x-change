# Cockpit Mutation Wave 4G — Durable Activity Journal Handoff Status Persistence Adapter

Status: Implemented

Date: 2026-07-10

## Scope

This checkpoint adds an opt-in database-backed status persistence adapter for durable Cockpit operator issuance activity journal handoff results.

It updates only the local durable activity journal handoff status projection. It does not invoke the journal handoff, write to x-journal, execute actions, send feedback, call providers, or change issuer/voucher behavior.

## Implemented

- Added `DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector`.
- Added config seam `x-change.cockpit.operator_issuance_activity.journal_handoff_status_projector`.
- Added the database projector to `available_journal_handoff_status_projectors`.
- Updated the service-provider binding so `CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract` resolves to the null projector by default or to the configured projector when explicitly enabled.

## Runtime Behavior

When explicitly configured, the database projector:

- locates a durable activity row by `activity_id`;
- updates only `journal_handoff_status`;
- writes a safe `metadata.journal_handoff` summary;
- preserves existing safe context;
- preserve action and feedback handoff statuses;
- returns `persisted` with `persists_status: true`.

If the durable activity row is missing, or the handoff result has no activity ID, the projector no-ops and returns non-persistent projection facts.

## Boundary

The default runtime remains `NullCockpitOperatorIssuanceActivityJournalHandoffStatusProjector`.

This slice does not connect the journal handoff adapter to the status projector. It only establishes the persistence adapter that the next invocation pipeline may call.

## Safe Metadata

The adapter persists only a whitelisted handoff metadata summary:

- `reference_number`
- `event_type`
- `idempotency_key`
- `exception`

Unsafe raw provider payloads are not persisted by this adapter.

## Non-Goals

- No default durable status persistence.
- No invocation pipeline.
- No queue job.
- No retry orchestration.
- No x-journal runtime call.
- No journal write.
- No action execution.
- No feedback delivery.
- No provider call.
- No wallet access.
- No voucher execution change.
- No lifecycle truth ownership.
- No raw payload exposure.
- No UI changes.
- No mutation controls.
- No money movement.

## Tests

- `tests/Feature/Cockpit/CockpitOperatorIssuanceActivityJournalHandoffStatusPersistenceAdapterTest.php`
- `tests/Unit/Architecture/CockpitDurableActivityJournalHandoffStatusPersistenceAdapterTest.php`

## Next checkpoint

Cockpit Mutation Wave 4H — Durable Activity Journal Handoff Invocation Pipeline.

Recommended scope:

- invoke the configured journal handoff after durable activity recording;
- invoke the configured status projector with the handoff result;
- keep failures non-blocking;
- keep all behavior opt-in behind existing configuration;
- do not add UI changes.
