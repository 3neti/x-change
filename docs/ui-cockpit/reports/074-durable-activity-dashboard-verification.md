# Cockpit Mutation Wave 3L — Durable Activity Dashboard Verification

Status: Implemented

Date: 2026-07-10

## Scope

This checkpoint verifies that the existing Cockpit dashboard props can carry durable operator issuance activity read-model data.

No production source changes were required.

## Verified

- Dashboard props remain `not_wired` by default when durable activity persistence is disabled.
- Dashboard props become `available` when:
  - `x-change.cockpit.operator_issuance_activity.repository` is explicitly configured, and
  - durable operator issuance activity records exist for the current operator.
- Existing dashboard prop key remains:
  - `operator_issuance_activity_read_model`
- Existing presentation shape remains read-only.
- Raw payload, provider payload, and wallet fields remain absent from dashboard props.

## UI Impact

No Vue components, pages, routes, TypeScript contracts, package assets, or host-published assets were changed.

The existing Operator Issuance Activity dashboard panel can now show durable records when persistence is explicitly configured and records exist.

## Non-Goals

- No new UI controls.
- No mutation controls.
- No journal writes.
- No x-action execution.
- No x-feedback delivery.
- No provider calls.
- No wallet access.
- No voucher execution changes.
- No lifecycle truth ownership.
- No raw payload exposure.
- No money movement.

## Tests

- Verification:
  - `php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitOperatorIssuanceActivityDashboardVerificationTest.php`
  - Result: `2 passed, 19 assertions`

## Next Recommended Checkpoint

Cockpit Mutation Wave 3M — Durable Activity Host Publish / Manual Verification

Recommended scope:

- publish package assets only if needed
- run Cockpit asset drift guard
- verify the dashboard manually with persistence disabled
- optionally verify dashboard activity with persistence enabled in a local test context
- do not add new mutation behavior
