# Cockpit Mutation Wave 4N — Durable Activity Diagnostic Fixture / Seeded Visual Verification Plan

Status: Plan recorded

Date: 2026-07-10

## Scope

This checkpoint defines the safe local-only fixture path needed to unblock visual verification of the populated journal handoff operator diagnostic UI.

The previous checkpoint, Wave 4M, confirmed that `/x/cockpit` renders and remains read-only, but diagnostic-specific visual confirmation was blocked because the dashboard had no durable operator issuance activity data.

This checkpoint is a plan. It does not create the fixture, seed the database, add an Artisan command, add a seeder, add a UI control, or mutate runtime behavior.

## Problem

The current dashboard state shows:

```text
No operator issuance activity available
Activity recording is not wired yet. Quick Generate can still use the existing issuance path.
```

That state is safe, but it cannot prove that the populated diagnostic UI renders correctly.

The populated diagnostic state requires a durable operator issuance activity record containing safe journal handoff metadata:

```text
metadata.journal_handoff
metadata.journal_handoff.diagnostic
```

## Required Fixture Goal

Create a future local-only fixture or seed path that inserts one safe durable operator issuance activity record with:

- an `activity_id`;
- an operator-safe label;
- a subject reference for a sample Pay Code;
- a non-sensitive status;
- a correlation ID;
- `journal_handoff_status`;
- safe `metadata.journal_handoff` summary;
- safe `metadata.journal_handoff.diagnostic` summary;
- redaction flags proving unsafe payloads are not exposed.

The fixture exists only to let a human reviewer see the populated dashboard card and confirm that:

- journal handoff evidence renders;
- operator diagnostic metadata renders;
- the diagnostic section is read-only;
- no retry button is visible;
- no mutation control is visible;
- no raw payload is visible;
- no provider payload is visible;
- no wallet data is visible;
- no secret, token, credential, OTP, or recipient secret is visible.

## Recommended Implementation Shape

The next implementation checkpoint should add one local-only command or test fixture helper.

Recommended command shape:

```text
php artisan x-change:cockpit:seed-diagnostic-activity --local-only
```

Required behavior:

- refuse to run in production;
- require an explicit `--local-only` flag;
- write only to the package-owned durable activity table;
- use the existing durable activity DTO/repository/model shape;
- populate only operator-safe fields;
- insert deterministic fixture metadata that is obviously synthetic;
- expose no raw provider, wallet, credential, token, OTP, recipient secret, or funding payload;
- avoid invoking x-journal;
- avoid creating journal entries;
- avoid executing actions;
- avoid sending feedback;
- avoid calling providers;
- avoid voucher mutation;
- avoid wallet access;
- avoid money movement.

## Fixture Data Policy

Allowed fixture values:

- synthetic activity IDs;
- synthetic operator labels;
- synthetic Pay Code references;
- synthetic journal reference numbers;
- synthetic event type names;
- synthetic idempotency keys;
- safe diagnostic labels and descriptions;
- explicit read-only and mutation-disabled flags.

Forbidden fixture values:

- real provider payloads;
- real wallet balances or account identifiers;
- real credentials;
- real tokens;
- OTP values;
- recipient secrets;
- raw request payloads;
- raw idempotency keys;
- bank details;
- provider callback payloads.

## Proposed Synthetic Diagnostic Metadata

The fixture should use safe metadata similar to:

```php
[
    'journal_handoff' => [
        'status' => 'recorded',
        'writes_journal' => true,
        'source' => 'local_fixture',
        'reason' => 'visual_verification',
        'reference_number' => 'ERN-LOCAL-COCKPIT-0001',
        'event_type' => 'cockpit.operator_issuance_activity.fixture',
        'idempotency_key' => 'fixture-redacted-idempotency-key',
        'diagnostic' => [
            'label' => 'Journal handoff recorded',
            'description' => 'Synthetic local fixture for visual verification only.',
            'operator_action' => 'No action required.',
            'read_only' => true,
            'retry_enabled' => false,
            'mutation_enabled' => false,
            'raw_payloads_exposed' => false,
        ],
    ],
]
```

This metadata is display evidence only. It must not be treated as journal truth.

## Visual Verification After Fixture

After the fixture exists and is seeded locally, the human reviewer should open:

```text
http://x-change-sandbox.test/x/cockpit
```

Expected populated evidence:

- `Operator Issuance Activity`;
- `Quick Generate evidence`;
- `Journal handoff`;
- `Journal entry`;
- `Writes journal`;
- `Source`;
- `Reason`;
- `Reference`;
- `Event`;
- `Operator diagnostic`;
- `Diagnostic: Journal handoff recorded`;
- `Action: No action required.`;
- `Read-only: yes`.

Expected absent evidence:

- retry button;
- mutation control;
- raw payload;
- provider payload;
- wallet data;
- secret;
- token;
- credential;
- OTP;
- recipient secret.

## Boundary

This checkpoint did not add:

- fixture command;
- seeder;
- migration;
- model changes;
- repository changes;
- database writes;
- frontend changes;
- host-published asset changes;
- routes;
- controllers;
- public APIs;
- browser automation dependencies;
- screenshots;
- x-journal calls;
- journal writes;
- handoff retries;
- queue jobs;
- action execution;
- feedback delivery;
- provider calls;
- wallet access;
- voucher mutation;
- lifecycle truth ownership;
- raw payload exposure;
- mutation controls;
- money movement.

## Next Recommended Checkpoint

```text
Cockpit Mutation Wave 4O — Local Durable Activity Diagnostic Fixture Implementation
```

Recommended scope:

- add the local-only fixture command or helper;
- protect it with production refusal and explicit `--local-only`;
- write one safe durable activity record through existing package-owned storage seams;
- test that unsafe fields are absent;
- verify dashboard read models can hydrate the fixture;
- do not publish or claim visual pass until the human reviewer confirms the populated UI.
