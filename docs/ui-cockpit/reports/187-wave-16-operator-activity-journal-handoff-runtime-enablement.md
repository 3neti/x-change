# Cockpit Wave 16 — Operator Activity Journal Handoff Runtime Enablement

Date: 2026-07-11

## Objective

Enable and verify the Cockpit operator issuance activity journal handoff path so Quick Generate durable activity can be recorded into x-journal when explicitly configured.

## Boundary

Wave 16 does not make journal writes the production default.

The default remains safe:

```text
repository: null
recorder: null
journal_handoff: null
journal_handoff_status_projector: null
```

Journal writes occur only when runtime configuration opts in.

## Completed Slices

### Wave 16A — Real x-journal Adapter Smoke

Added focused coverage proving `XJournalCockpitOperatorIssuanceActivityJournalHandoff` records Cockpit operator issuance activity through x-journal's real `ExecutionJournalRecorder`.

Verified:

- `cockpit.operator_issuance_activity.recorded` event type.
- operator actor projection.
- Pay Code subject projection.
- correlation and causation IDs.
- idempotent duplicate handoff returns the existing journal entry.
- sensitive metadata is not exposed.

### Wave 16B — Runtime Profile Key Resolution

Added runtime profile-key resolution in `XChangeServiceProvider`.

Supported keys:

```text
repository=database
recorder=database
journal_handoff=x-journal
journal_handoff_status_projector=database
```

Direct class-name configuration remains supported.

### Wave 16C — Real Quick Generate Runtime Handoff

Added feature coverage proving the existing Quick Generate mutation route:

- still hands off issuance to existing `GeneratePayCode`;
- records durable Cockpit operator activity when configured;
- writes an x-journal entry through the real x-journal recorder;
- persists durable activity `journal_handoff_status=recorded`;
- does not duplicate journal entries on idempotent replay.

### Wave 16D — Read Model Exposure

Added feature coverage proving dashboard props expose:

```text
handoffs.journal = recorded
writes_journal = true
journal_entry_id = ...
reference_number = ...
event_type = cockpit.operator_issuance_activity.recorded
```

Action and feedback remain `not_wired`.

### Wave 16E — Dusk Dashboard Smoke

Added host Dusk coverage using the existing safe local diagnostic fixture command.

Verified browser-visible dashboard copy:

```text
Pay Code PC-DUSK-JOURNAL issued
journal: recorded
Writes journal: yes
Journal entry: journal-entry-local-fixture
Diagnostic: Journal recorded
Reference: ERN-LOCAL-COCKPIT-0001
```

## Runtime Configuration

For local journal-enabled activity runtime:

```env
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY=database
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_RECORDER=database
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_JOURNAL_HANDOFF=x-journal
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_JOURNAL_HANDOFF_STATUS_PROJECTOR=database
```

Class names are still accepted for backward compatibility.

## Test Results

Focused package tests:

```text
CockpitOperatorIssuanceActivityXJournalHandoffTest.php
CockpitOperatorIssuanceActivityRuntimeProfileResolutionTest.php
CockpitQuickGenerateXJournalRuntimeTest.php
CockpitOperatorIssuanceActivityXJournalReadModelTest.php
```

Host browser test:

```text
CockpitDashboardJournalRecordedSmokeTest.php
```

All focused checks passed during the wave.

## UI Impact

No new UI component was added.

The dashboard can now show real or fixture journal-recorded activity through the existing Operator Issuance Activity panel when durable activity storage and journal handoff metadata are present.

## Remaining Boundaries

- Journal writes remain opt-in.
- Action handoff remains `not_wired`.
- Feedback handoff remains `not_wired`.
- No provider calls are added.
- No wallet mutation behavior is changed.
- No voucher execution behavior is changed.
- No campaign mutation is added.

## Next Recommended Wave

```text
Cockpit Wave 17 — Operator Activity Action Handoff Runtime Enablement
```

The next logical state transition is:

```text
journal: recorded
action: not_wired → action: available / composed
feedback: not_wired
```
