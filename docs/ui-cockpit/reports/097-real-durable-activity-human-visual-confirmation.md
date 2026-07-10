# Cockpit Mutation Wave 5C — Real Durable Activity Human Visual Confirmation

Status: Pass — accepted by human

Date: 2026-07-11

## Scope

This checkpoint records human visual confirmation that Cockpit renders real durable operator issuance activity created by the local Wave 5B Quick Generate verification.

Verified route:

```text
http://x-change-sandbox.test/x/cockpit
```

This is a confirmation record only. It does not introduce new UI behavior, backend behavior, routes, controllers, APIs, mutation controls, journal writes, action execution, feedback delivery, provider calls, wallet access, voucher mutation, or money movement.

## Human Evidence Received

The human reviewer confirmed the expected real generated Pay Code activity appears in the Operator Issuance Activity panel and pasted:

```text
Operator Issuance Activity

Quick Generate evidence
presentation-only
Pay Code MCPC issued

PHP 25 issued through Quick Generate

issued
Journal
journal: not_wired
Action
action: not_wired
Feedback
feedback: not_wired
Writes journal
Writes journal: no
Source
Source: durable-operator-issuance-activity-read-model
Reason
Reason: Journal handoff status is projected from durable Cockpit activity storage.
Operator diagnostic
Diagnostic: Journal handoff not wired
Journal handoff status is projected from durable Cockpit activity storage.
Action: configure_when_ready
Read-only: yes
Correlation: corr-cockpit-real-activity-5b
Open Pay Code
```

The same visual pass also showed the prior local diagnostic fixture remains visible:

```text
Pay Code PC-LOCAL-DIAGNOSTIC issued

PHP 25.00 issued through Quick Generate

issued
Journal
journal: recorded
Action
action: not_wired
Feedback
feedback: not_wired
Journal entry
Journal entry: journal-entry-local-fixture
Writes journal
Writes journal: yes
Source
Source: local_fixture
Reason
Reason: Synthetic local fixture for Cockpit diagnostic visual verification.
Reference
Reference: ERN-LOCAL-COCKPIT-0001
Event
Event: cockpit.operator_issuance_activity.fixture
Operator diagnostic
Diagnostic: Journal recorded
The durable activity was handed to the journal and a journal entry identifier is available for read-only inspection.
Action: none
Read-only: yes
Correlation: corr-local-cockpit-diagnostic
Open Pay Code
```

## Human Decision

Decision:

```text
Pass — accepted by human
```

## Pass Criteria Verification

| Surface | Expected Evidence | Result | Evidence / Notes |
| --- | --- | --- | --- |
| Cockpit Dashboard | `/x/cockpit` renders | Pass | Human supplied rendered dashboard content. |
| Operator Issuance Activity panel | `Operator Issuance Activity` and `Quick Generate evidence` render | Pass | Human scrape shows both labels. |
| Real generated Pay Code activity | `Pay Code MCPC issued` renders | Pass | Human scrape shows `Pay Code MCPC issued`. |
| Real generated amount | `PHP 25 issued through Quick Generate` renders | Pass | Human scrape shows the expected amount and route context. |
| Journal handoff status | `journal: not_wired` and `Writes journal: no` render for real activity | Pass | Human scrape confirms journal is not wired and no write is claimed. |
| Action handoff status | `action: not_wired` renders | Pass | Human scrape confirms no action execution is claimed. |
| Feedback handoff status | `feedback: not_wired` renders | Pass | Human scrape confirms no feedback delivery is claimed. |
| Operator diagnostic | `Diagnostic: Journal handoff not wired`, `Action: configure_when_ready`, and `Read-only: yes` render | Pass | Human scrape confirms read-only diagnostic display. |
| Correlation | `corr-cockpit-real-activity-5b` renders | Pass | Human scrape confirms correlation visibility. |
| Unsafe payload exposure | No raw payload, provider payload, wallet data, secret, token, credential, OTP, or recipient secret visible | Pass | Human scrape contains only operator-safe activity facts. |
| Mutation controls | No retry or new mutation controls visible | Pass | Human scrape contains `Open Pay Code` navigation only. |

## Interpretation

This confirms the real local opt-in path:

```text
Existing Cockpit Quick Generate issuance
    ↓
Database durable activity recorder
    ↓
Database durable activity repository
    ↓
Cockpit read model
    ↓
Operator Issuance Activity panel
```

The fixture path also remains available for diagnostic comparison:

```text
Local diagnostic fixture
    ↓
Recorded synthetic journal handoff metadata
    ↓
Operator diagnostic display
```

## Boundary Confirmation

This checkpoint did not add:

- screenshots;
- browser automation dependencies;
- source behavior changes;
- frontend behavior changes;
- host-published asset changes;
- route changes;
- controller changes;
- API changes;
- migrations;
- model changes;
- repository changes;
- recorder changes;
- production default durable activity recording;
- new Quick Generate semantics;
- voucher execution changes;
- journal writes;
- action execution;
- feedback delivery;
- provider calls outside the existing Quick Generate issuance path;
- direct wallet access;
- direct wallet mutation;
- lifecycle truth ownership;
- raw payload exposure;
- retry controls;
- new mutation controls;
- campaign mutation;
- money movement.

The real `MCPC` activity correctly shows `Writes journal: no`. The fixture `PC-LOCAL-DIAGNOSTIC` activity still shows `Writes journal: yes` because it is synthetic diagnostic metadata from the local fixture, not a journal write triggered by page viewing.

## Supporting Prior Verification

This pass depends on Wave 5B, which verified:

- repository and recorder config were enabled locally;
- published Cockpit assets were synchronized;
- real Quick Generate returned HTTP 201;
- Pay Code `MCPC` was generated for `PHP 25`;
- durable activity was recorded for operator `5`;
- raw idempotency key was not persisted;
- redaction flags remained false;
- Cockpit read model hydrated `Pay Code MCPC issued`.

## Next Recommended Checkpoint

```text
Cockpit Mutation Wave 5D — Durable Activity Local Opt-In Closure / Cleanup Decision
```

Recommended scope:

- decide whether to keep the local recorder enabled for continued manual testing;
- decide whether to remove the synthetic diagnostic fixture from the local database after visual verification;
- decide whether to create a separate cleanup slice for the Brick\Math float deprecation;
- do not production-enable durable activity recording by default yet;
- do not add journal writes, action execution, feedback delivery, retry controls, provider changes, wallet changes, or new mutation controls.
