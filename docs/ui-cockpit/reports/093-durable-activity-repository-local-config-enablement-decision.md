# Cockpit Mutation Wave 4Q — Durable Activity Repository Local Config Enablement Decision

Status: Enabled locally

Date: 2026-07-11

## Scope

This checkpoint records the local host decision to enable the database-backed durable operator issuance activity repository so the seeded diagnostic fixture can be rendered in Cockpit.

This is a local environment change only. The `.env` change is intentionally not committed.

## Local Environment Entry Added

```env
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository
```

## Commands Executed

Configuration cache clear:

```text
php artisan config:clear
```

Result:

```text
Configuration cache cleared successfully.
```

Configuration verification:

```text
php artisan config:show x-change.cockpit.operator_issuance_activity.repository
```

Result:

```text
x-change.cockpit.operator_issuance_activity.repository  LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository
```

Fixture re-seed:

```text
php artisan x-change:cockpit:seed-diagnostic-activity --local-only --operator-id=5 --json
```

Result:

```json
{
  "seeded": true,
  "local_only": true,
  "activity_id": "fixture-cockpit-journal-diagnostic-activity",
  "operator_id": "5",
  "code": "PC-LOCAL-DIAGNOSTIC",
  "journal_handoff_status": "recorded",
  "dashboard_ready": true,
  "dashboard_repository": "LBHurtado\\XChange\\Services\\Cockpit\\DatabaseCockpitOperatorIssuanceActivityRepository",
  "next_step": "Open /x/cockpit and verify the populated Operator Issuance Activity diagnostic card.",
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

## Database Verification

The seeded fixture row exists with:

```text
activity_id: fixture-cockpit-journal-diagnostic-activity
actor_id: 5
source: cockpit.local-diagnostic-fixture
subject_reference: PC-LOCAL-DIAGNOSTIC
journal_handoff_status: recorded
action_handoff_status: not_wired
feedback_handoff_status: not_wired
reference_number: ERN-LOCAL-COCKPIT-0001
event_type: cockpit.operator_issuance_activity.fixture
raw_payloads_exposed: false
provider_payloads_exposed: false
wallet_data_exposed: false
recipient_secrets_exposed: false
```

## Operator Scope Correction

Human browser verification after the initial 4Q enablement showed the Cockpit dashboard still rendered:

```text
No durable operator issuance activity available
Durable activity storage is configured, but no matching activity has been recorded yet.
```

Root cause:

```text
The local fixture was seeded with actor_id `local-fixture-operator`, while the authenticated local operator `admin@disburse.cash` resolves to operator id `5`.
```

Cockpit intentionally filters operator issuance activity by the authenticated operator id. The fix was to make the local fixture command operator-scoped:

```text
php artisan x-change:cockpit:seed-diagnostic-activity --local-only --operator-id=5 --json
```

Verification:

```text
activity_id: fixture-cockpit-journal-diagnostic-activity
actor_id: 5
subject_reference: PC-LOCAL-DIAGNOSTIC
journal_handoff_status: recorded
```

This was a fixture/testability correction only. It did not change dashboard filtering semantics, production defaults, repository behavior, issuer activity recording, journal writes, action execution, feedback delivery, provider calls, wallet access, voucher mutation, raw payload exposure, or money movement.

## Decision

Decision:

```text
Enable the database durable activity repository locally for visual verification.
```

Rationale:

- Wave 4P proved the fixture existed but Cockpit could not display it while the repository config was `null`.
- The database repository is already package-owned and covered by the durable activity repository/read-model tests.
- The fixture command is local-only, refuses production, and reports no journal writes, action execution, feedback sending, provider calls, voucher mutation, wallet touching, money movement, or raw payload exposure.
- This enables a human reviewer to verify the populated diagnostic card without changing production defaults.

## Human Visual Verification Handoff

Open:

```text
http://x-change-sandbox.test/x/cockpit
```

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

- committed `.env` changes;
- production default changes;
- package source behavior;
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
Cockpit Mutation Wave 4R — Seeded Diagnostic Fixture Human Visual Confirmation
```

Recommended scope:

- have the human reviewer open `/x/cockpit`;
- confirm the populated activity card is visible;
- confirm journal handoff and operator diagnostic evidence renders;
- confirm no unsafe payloads or mutation controls are visible;
- record pass/block/fail evidence.
