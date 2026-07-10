# Cockpit Mutation Wave 4E — Durable Activity Journal Handoff Status Persistence Decision

Status: Implemented

Date: 2026-07-10

## Decision

Decision: persist journal handoff status in a later explicit implementation slice.

Do not update durable activity rows inside `XJournalCockpitOperatorIssuanceActivityJournalHandoff`.

## Rationale

Wave 4D added an opt-in x-journal adapter that records a Cockpit durable activity fact and returns `CockpitOperatorIssuanceActivityJournalHandoffResultData`.

That adapter should stay focused on the external audit handoff:

```text
CockpitOperatorIssuanceActivityItemData
    ↓
Journal payload mapper
    ↓
x-journal ExecutionJournalRecorder
    ↓
CockpitOperatorIssuanceActivityJournalHandoffResultData
```

Persisting the handoff result back into x-change durable activity rows is a separate concern. Mixing repository mutation into the x-journal adapter would make the adapter responsible for both external audit recording and local Cockpit read-model state mutation.

## Approved Direction

A future status projector should own durable status persistence.

The future status projector should:

- accept `CockpitOperatorIssuanceActivityJournalHandoffResultData`;
- locate the existing durable activity record by `activity_id`;
- persist `journal_handoff_status`;
- preserve existing action and feedback handoff statuses;
- preserve existing safe context and redaction state;
- store only safe journal metadata, such as journal entry ID, reference number, source, reason, and event type;
- treat failed handoffs as non-blocking activity status facts;
- no-op when no durable activity row exists.

## Current Durable Field

Durable activity records already include:

```text
journal_handoff_status
```

The field remains available for read models, but this checkpoint does not write it after handoff execution.

## Boundary

This checkpoint intentionally keeps:

- `XJournalCockpitOperatorIssuanceActivityJournalHandoff` free of `CockpitOperatorIssuanceActivityRepositoryContract`;
- `CockpitOperatorIssuanceActivityRepositoryContract` free of journal-specific update methods;
- x-journal adapter behavior non-blocking;
- durable status mutation deferred to the next explicit contract slice.

## Non-Goals

- No production code changes.
- No repository update method.
- No durable activity row mutation.
- No migration change.
- No queue job.
- No retry orchestration.
- No action execution.
- No feedback delivery.
- No provider calls.
- No wallet access.
- No voucher execution changes.
- No lifecycle truth ownership.
- No raw payload exposure.
- No UI changes.
- No money movement.

## Next checkpoint

Cockpit Mutation Wave 4F — Durable Activity Journal Handoff Status Projector Contract.

Recommended scope:

- add a package-local status projector contract;
- add a null/no-op implementation;
- prove the x-journal adapter remains free of repository mutation;
- do not persist handoff status yet unless explicitly authorized in the implementation slice.
