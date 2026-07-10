# Cockpit Mutation Wave 4I — Durable Activity Journal Handoff Read Model Exposure

Status: Implemented

Date: 2026-07-10

## Scope

This checkpoint exposes persisted journal handoff status and safe journal handoff summary fields through the existing operator issuance activity read model.

The exposure is read-only dashboard evidence. It does not create journal entries, retry handoffs, enqueue jobs, or introduce new mutation controls.

## Implemented

- Updated `DurableCockpitOperatorIssuanceActivityReadModelProvider` to hydrate `CockpitOperatorIssuanceActivityJournalHandoffResultData` from persisted durable activity metadata.
- Updated `DefaultCockpitOperatorIssuanceActivityPresenter` to expose a safe `metadata.journal_handoff` summary.
- Updated `CockpitOperatorIssuanceActivityPanel` to render safe journal handoff evidence:
  - journal entry ID;
  - writes-journal flag;
  - source;
  - reason;
  - reference number;
  - event type.
- Updated Cockpit TypeScript types for the safe journal handoff summary shape.

## Safe Summary Fields

The read model may expose only:

```text
status
journal_entry_id
writes_journal
source
reason
metadata.reference_number
metadata.event_type
metadata.idempotency_key
metadata.exception
```

Unsafe metadata, provider payloads, raw payloads, credentials, tokens, wallet data, and recipient secrets remain excluded.

## Boundary

This slice is read-model and presentation exposure only.

It does not:

- write to x-journal;
- retry journal handoff;
- invoke journal handoff;
- create queue jobs;
- expose raw journal payloads;
- expose provider payloads;
- expose wallet data;
- execute actions;
- send feedback;
- move money;
- own lifecycle truth.

## UI Change

Yes. The existing Cockpit dashboard operator issuance activity card can now show a read-only journal handoff evidence box when safe journal handoff metadata exists.

No buttons, retry controls, mutation controls, or workflow actions were added.

## Tests

- `tests/Feature/Cockpit/CockpitOperatorIssuanceActivityDurableReadModelAdapterTest.php`
- `tests/Unit/Cockpit/CockpitOperatorIssuanceActivityPresentationTest.php`
- `tests/frontend/cockpit/CockpitDashboardHydration.test.ts`
- `tests/Unit/Architecture/CockpitDurableActivityJournalHandoffReadModelExposureTest.php`

## Next checkpoint

Cockpit Mutation Wave 4J — Durable Activity Journal Handoff Operator Diagnostics.

Recommended scope:

- add read-only diagnostic classification for journal handoff evidence;
- keep diagnostics safe and non-actionable;
- do not add retry or mutation controls;
- do not expose raw journal/provider payloads.
