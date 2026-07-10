# Cockpit Mutation Wave 4O — Local Durable Activity Diagnostic Fixture Implementation

Status: Implemented

Date: 2026-07-10

## Scope

This checkpoint implements the local-only Cockpit durable activity diagnostic fixture planned in Wave 4N.

It adds a guarded Artisan command that can create one synthetic durable operator issuance activity record for browser verification of the populated journal handoff diagnostic UI.

## Added Command

```text
php artisan x-change:cockpit:seed-diagnostic-activity --local-only
```

Optional flags:

```text
--activity-id=fixture-cockpit-journal-diagnostic-activity
--code=PC-LOCAL-DIAGNOSTIC
--json
--pretty
```

## Safety Gates

The command:

- refuses to run without `--local-only`;
- refuses to run when `app.env` or the application environment is `production`;
- writes only to the package-owned durable activity table through `DatabaseCockpitOperatorIssuanceActivityRepository`;
- inserts synthetic fixture data only;
- does not call x-journal;
- does not create journal entries;
- does not execute actions;
- does not send feedback;
- does not call providers;
- does not mutate vouchers;
- does not access wallets;
- does not move money.

## Seeded Fixture Shape

The seeded record uses:

```text
activity_id: fixture-cockpit-journal-diagnostic-activity
actor_id: local-fixture-operator
source: cockpit.local-diagnostic-fixture
subject_reference: PC-LOCAL-DIAGNOSTIC
journal_handoff_status: recorded
action_handoff_status: not_wired
feedback_handoff_status: not_wired
correlation_id: corr-local-cockpit-diagnostic
```

It includes safe synthetic metadata:

```text
metadata.journal_handoff.status
metadata.journal_handoff.journal_entry_id
metadata.journal_handoff.writes_journal
metadata.journal_handoff.source
metadata.journal_handoff.reason
metadata.journal_handoff.metadata.reference_number
metadata.journal_handoff.metadata.event_type
metadata.journal_handoff.metadata.idempotency_key
```

The diagnostic metadata shown in the UI is still produced by the existing read-model/presenter pipeline, not hardcoded as UI truth.

## Dashboard Readiness Note

The command writes the fixture record directly through the database durable activity repository.

For `/x/cockpit` to display it, the host must also use the database repository for the operator issuance activity read model:

```text
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository
```

If config is cached, clear or refresh config before browser verification.

## Verification

Focused red baseline:

```text
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitDurableActivityDiagnosticFixtureCommandTest.php
```

Initial result:

```text
4 failed because x-change:cockpit:seed-diagnostic-activity did not exist.
```

Focused implementation result:

```text
4 passed, 59 assertions
```

Covered behavior:

- missing `--local-only` fails and writes nothing;
- production environment fails and writes nothing;
- local fixture seeding creates one safe durable activity record;
- unsafe metadata fields are absent;
- redaction flags remain false;
- the configured Cockpit read model hydrates the seeded record;
- the hydrated presentation includes read-only journal handoff diagnostic metadata;
- no retry or mutation permission is introduced.

## UI Impact

No package Vue files changed.

No host-published assets changed.

The next browser verification can now create data that lets a human reviewer see the populated diagnostic UI.

## Boundary

This checkpoint did not add:

- frontend changes;
- host-published asset changes;
- routes;
- controllers;
- public APIs;
- migrations;
- model changes;
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
- retry controls;
- mutation controls;
- money movement.

## Next Recommended Checkpoint

```text
Cockpit Mutation Wave 4P — Seeded Diagnostic Fixture Host Verification / Human Visual Handoff
```

Recommended scope:

- run the fixture command in the local host app only if the operator wants populated browser evidence;
- confirm the database repository config is active for Cockpit operator issuance activity;
- open `/x/cockpit`;
- verify the populated Operator Issuance Activity diagnostic card;
- record pass/block/fail human evidence.
