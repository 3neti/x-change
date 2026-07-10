# Cockpit Mutation Wave 4C — Durable Activity Journal Payload Mapping Baseline

Status: Implemented

Date: 2026-07-10

## Scope

This checkpoint adds a pure mapping layer from Cockpit durable activity facts to a journal-ready payload.

It does not call x-journal and does not write journal entries.

## Implemented

- Added `CockpitOperatorIssuanceActivityJournalPayloadData`.
- Added `CockpitOperatorIssuanceActivityJournalPayloadMapper`.
- Mapped `CockpitOperatorIssuanceActivityItemData` into:
  - event name
  - domain
  - stable idempotency key
  - actor
  - subject
  - references
  - operator-safe payload
  - redaction metadata
- Proved raw payload, provider payload, wallet, recipient secret, OTP, and funding source data are excluded.
- Proved missing correlation/operator details degrade deterministically.

## Payload Boundary

The mapper emits a package-local payload:

```text
CockpitOperatorIssuanceActivityJournalPayloadData
```

It is intentionally not an x-journal DTO yet. The next implementation slice should adapt this package-local payload into the chosen x-journal recording surface.

## Non-Goals

- No x-journal runtime calls.
- No journal writes.
- No x-journal package DTO normalization.
- No journal handoff implementation.
- No durable activity handoff status mutation.
- No migrations.
- No queue jobs.
- No retries.
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
  - `php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/CockpitOperatorIssuanceActivityJournalPayloadMapperTest.php`
  - Result: `3 failed, 0 assertions`
- Focused implementation:
  - `php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/CockpitOperatorIssuanceActivityJournalPayloadMapperTest.php`
  - Result: `3 passed, 19 assertions`

## Next Recommended Checkpoint

Cockpit Mutation Wave 4D — Durable Activity Journal Handoff Adapter Baseline

Recommended scope:

- introduce an opt-in handoff implementation that consumes the package-local payload mapper
- adapt to the chosen x-journal recording surface
- keep failure non-blocking
- prove duplicate/idempotent journal inputs do not duplicate records
- do not update durable activity handoff status yet
