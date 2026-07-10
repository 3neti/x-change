# Cockpit Mutation Wave 4A — Durable Activity Journal Handoff Implementation Decision

Status: Implemented

Date: 2026-07-10

## Decision

Proceed with a future opt-in journal handoff for durable operator issuance activity.

Do not write journal entries in this checkpoint.

## Rationale

Durable Cockpit activity currently records operator-safe Quick Generate evidence inside x-change. x-journal is the Settlement OS audit layer and already provides observational recording seams for operator actions, execution outcomes, provider callbacks, reconciliation, campaigns, and Cockpit read-side evidence.

The correct boundary is:

```text
Quick Generate
    ↓
Durable Cockpit Activity Record
    ↓
Optional Journal Handoff
    ↓
x-journal append-only record
```

The journal handoff must be observational. It records that an operator-visible Cockpit activity happened. It must not authorize issuance, execute actions, complete CTAs, send feedback, retry issuance, alter voucher state, or move money.

## Target Shape

Future implementation should introduce an opt-in handoff implementation behind the existing contract:

```text
CockpitOperatorIssuanceActivityJournalHandoffContract
```

The default remains:

```text
NullCockpitOperatorIssuanceActivityJournalHandoff
```

The future opt-in implementation should:

- accept `CockpitOperatorIssuanceActivityItemData`
- normalize the activity into an x-journal operator/action or Cockpit activity event
- provide a stable idempotency key derived from:
  - activity ID
  - correlation ID
  - Pay Code
  - operator ID
- record only operator-safe payloads
- return `CockpitOperatorIssuanceActivityJournalHandoffResultData`
- update durable activity handoff status only in a later explicit slice if that mutation is separately authorized

## x-journal Candidate Surfaces

Observed package surfaces that may be used by a later implementation:

- `JournalEventRecorder`
- `OperatorActionJournalRecorder`
- `JournalEventData`
- `JournalEventTransformerRegistry`
- `CockpitJournalReader`

The exact target should be verified in the next implementation slice against current x-journal contracts.

## Configuration Policy

Journal handoff must be disabled by default.

Future runtime opt-in should use a package-owned config seam similar to durable activity storage:

```php
x-change.cockpit.operator_issuance_activity.journal_handoff
```

The handoff implementation must degrade safely to `not_wired` if x-journal is unavailable or not configured.

## Non-Goals

- No journal writes in this checkpoint.
- No x-journal runtime calls.
- No new migrations.
- No queue jobs.
- No retries.
- No action execution.
- No feedback delivery.
- No provider calls.
- No wallet access.
- No voucher execution changes.
- No lifecycle truth ownership.
- No raw payload exposure.
- No money movement.
- No UI changes.

## Required Tests for the Next Implementation Slice

The next implementation slice should start with failing tests proving:

1. default handoff remains `not_wired`
2. configured handoff writes exactly one journal fact for a durable activity
3. duplicate activity/idempotency input does not duplicate journal facts
4. raw payload, provider payload, wallet, recipient secret, OTP, and funding source remain excluded
5. handoff failure is non-blocking to Quick Generate
6. x-journal absence degrades to `not_wired`
7. journal handoff does not execute actions, send feedback, mutate vouchers, or move money

## Next Recommended Checkpoint

Cockpit Mutation Wave 4B — Durable Activity Journal Handoff Contract / Null Runtime Configuration

Recommended scope:

- add a config seam for journal handoff implementation
- keep null handoff as default
- add tests for default `not_wired` behavior and configured service resolution
- do not call x-journal yet
