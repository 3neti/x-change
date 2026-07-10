# Cockpit Mutation Wave 4D — Durable Activity Journal Handoff Adapter Baseline

Status: Implemented

## Scope

This checkpoint adds an opt-in x-journal adapter for Cockpit durable operator issuance activity.

## Implemented

- Added `XJournalCockpitOperatorIssuanceActivityJournalHandoff`.
- The adapter consumes `CockpitOperatorIssuanceActivityJournalPayloadMapper`.
- The adapter records through x-journal `ExecutionJournalRecorder`.
- The adapter creates `ExecutionJournalEntryData` with:
  - stable event type;
  - stable idempotency key;
  - operator actor;
  - Pay Code subject;
  - activity/correlation references;
  - sanitized payload and metadata.
- Replayed handoffs use x-journal idempotent replay semantics and return the existing journal entry.
- x-journal failures return `failed_non_blocking`.

## Boundaries

- No UI changes.
- No default runtime change; the null handoff remains the configured default.
- No durable activity status update yet.
- No Cockpit activity repository mutation.
- No action execution.
- No feedback delivery.
- No money movement.

## Tests

- `tests/Feature/Cockpit/CockpitOperatorIssuanceActivityXJournalHandoffAdapterTest.php`
- `tests/Unit/Architecture/CockpitDurableActivityJournalHandoffAdapterBaselineTest.php`

## Next checkpoint

Cockpit Mutation Wave 4E — Durable Activity Journal Handoff Status Persistence Decision.
