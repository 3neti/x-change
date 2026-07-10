# Cockpit Mutation Wave 4B — Durable Activity Journal Handoff Contract / Null Runtime Configuration

Status: Implemented

Date: 2026-07-10

## Scope

This checkpoint adds the runtime configuration seam for durable activity journal handoff implementations.

It does not call x-journal and does not write journal entries.

## Implemented

- Added `x-change.cockpit.operator_issuance_activity.journal_handoff`.
- Added `x-change.cockpit.operator_issuance_activity.available_journal_handoffs`.
- Updated `XChangeServiceProvider` so `CockpitOperatorIssuanceActivityJournalHandoffContract` resolves from config at runtime.
- Kept `NullCockpitOperatorIssuanceActivityJournalHandoff` as the default implementation.
- Proved a configured handoff service can be resolved without invoking x-journal.
- Proved the null handoff still returns `not_wired` with `writes_journal: false`.

## Runtime Boundary

Default:

```text
CockpitOperatorIssuanceActivityJournalHandoffContract
    → NullCockpitOperatorIssuanceActivityJournalHandoff
```

Future opt-in:

```text
x-change.cockpit.operator_issuance_activity.journal_handoff
    → concrete handoff implementation
```

## Non-Goals

- No x-journal runtime calls.
- No journal writes.
- No x-journal package DTO normalization.
- No migrations.
- No queue jobs.
- No retries.
- No durable activity handoff status mutation.
- No action execution.
- No feedback delivery.
- No provider calls.
- No wallet access.
- No voucher execution changes.
- No lifecycle truth ownership.
- No raw payload exposure.
- No UI changes.

## Tests

- Red baseline:
  - `php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitOperatorIssuanceActivityJournalHandoffRuntimeConfigurationTest.php`
  - Result: `1 failed, 2 passed, 7 assertions`
- Focused implementation:
  - `php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitOperatorIssuanceActivityJournalHandoffRuntimeConfigurationTest.php`
  - Result: `3 passed, 11 assertions`

## Next Recommended Checkpoint

Cockpit Mutation Wave 4C — Durable Activity Journal Payload Mapping Baseline

Recommended scope:

- introduce a pure mapper from `CockpitOperatorIssuanceActivityItemData` to a journal-ready payload
- keep mapper independent of x-journal runtime
- cover idempotency key, actor, subject, correlation, safe context, and redaction boundaries
- do not write journal entries yet
