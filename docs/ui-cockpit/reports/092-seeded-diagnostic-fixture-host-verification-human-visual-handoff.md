# Cockpit Mutation Wave 4P — Seeded Diagnostic Fixture Host Verification / Human Visual Handoff

Status: Blocked — host read model repository config is not enabled

Date: 2026-07-10

## Scope

This checkpoint verifies the Wave 4O local diagnostic fixture from the host app and prepares the human visual handoff for the populated Cockpit diagnostic UI.

This checkpoint does not change package source behavior or host configuration. It does not edit `.env`.

## Host URL

```text
http://x-change-sandbox.test/x/cockpit
```

## Commands Executed

Command registration:

```text
php artisan list x-change:cockpit
```

Result:

```text
x-change:cockpit:seed-diagnostic-activity
```

Current host read-model repository config:

```text
php artisan config:show x-change.cockpit.operator_issuance_activity.repository
```

Result:

```text
x-change.cockpit.operator_issuance_activity.repository ................ null
```

Published asset drift guard:

```text
php artisan x-change:doctor --assets --json
```

Result:

```text
checked 55, ok 55, stale 0, missing 0, extra 0
```

Fixture command:

```text
php artisan x-change:cockpit:seed-diagnostic-activity --local-only --json
```

Result:

```json
{
  "seeded": true,
  "local_only": true,
  "activity_id": "fixture-cockpit-journal-diagnostic-activity",
  "code": "PC-LOCAL-DIAGNOSTIC",
  "journal_handoff_status": "recorded",
  "dashboard_ready": false,
  "dashboard_repository": null,
  "next_step": "Set XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY to the database repository before browser verification.",
  "safety": {
    "writes_journal": false,
    "executes_action": false,
    "sends_feedback": false,
    "calls_provider": false,
    "mutates_voucher": false,
    "touches_wallet": false,
    "moves_money": false,
    "raw_payloads_exposed": false
  }
}
```

Cockpit route registration:

```text
php artisan route:list --path=x/cockpit
```

Result:

```text
6 routes
```

Host build:

```text
npm run build
```

Result:

```text
passed
```

Existing third-party Rolldown pure annotation warnings from `reka-ui` / `@vueuse` remain present.

## Database Verification

The local fixture row exists in:

```text
x_change_cockpit_operator_issuance_activities
```

Verified facts:

```text
activity_id: fixture-cockpit-journal-diagnostic-activity
source: cockpit.local-diagnostic-fixture
subject_reference: PC-LOCAL-DIAGNOSTIC
journal_handoff_status: recorded
action_handoff_status: not_wired
feedback_handoff_status: not_wired
reference_number: ERN-LOCAL-COCKPIT-0001
event_type: cockpit.operator_issuance_activity.fixture
```

## Current Handoff Decision

Current decision:

```text
Blocked — host read model repository config is not enabled
```

The fixture is seeded and safe, but `/x/cockpit` will not display the populated diagnostic card while:

```text
x-change.cockpit.operator_issuance_activity.repository = null
```

This is not a UI failure and not a fixture failure. It is the expected result of leaving durable activity read-model storage disabled by default.

## Required Local Config for Visual Verification

To visually verify the populated diagnostic card, enable the database repository in the local host environment:

```text
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository
```

If config is cached, run:

```text
php artisan config:clear
```

Then re-run:

```text
php artisan x-change:cockpit:seed-diagnostic-activity --local-only --json
```

Then open:

```text
http://x-change-sandbox.test/x/cockpit
```

## Expected Human Visual Evidence After Config Is Enabled

Expected visible populated evidence:

- `Operator Issuance Activity`;
- `Quick Generate evidence`;
- `PC-LOCAL-DIAGNOSTIC`;
- `Journal handoff`;
- `Journal entry`;
- `Writes journal`;
- `Source`;
- `Reason`;
- `Reference`;
- `Event`;
- `Operator diagnostic`;
- `Diagnostic: Journal recorded`;
- `Action: none`;
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

- host `.env` edits;
- host config changes;
- package source changes;
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

The fixture command itself reported all safety flags false for journal writes, action execution, feedback sending, provider calls, voucher mutation, wallet touching, money movement, and raw payload exposure.

## Next Recommended Checkpoint

```text
Cockpit Mutation Wave 4Q — Durable Activity Repository Local Config Enablement Decision
```

Recommended scope:

- decide whether to update local host `.env` for the database activity repository;
- if approved, update only local environment config;
- clear config cache if needed;
- re-run the fixture command;
- ask the human reviewer to visually confirm the populated diagnostic card.
