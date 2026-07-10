# Cockpit Mutation Wave 3M — Durable Activity Host Publish / Manual Verification

Status: Implemented

Date: 2026-07-10

## Scope

This checkpoint verifies host-published Cockpit assets after the durable activity backend/read-model slices.

No package frontend assets changed in Waves 3J–3L, so no publish action was required.

## Verification

Command:

```bash
php artisan x-change:doctor --assets --json
```

Result:

```text
success: true
checked: 55
ok: 55
stale: 0
missing: 0
extra: 0
```

Resolved Cockpit URL:

```text
http://x-change-sandbox.test/x/cockpit
```

## Manual Browser Expectation

With durable activity persistence disabled, the dashboard should continue to show the existing read-only Operator Issuance Activity empty state.

When durable activity persistence is explicitly configured and records exist, the existing Operator Issuance Activity panel can show durable Quick Generate evidence without adding new UI controls.

## UI Impact

No Vue components, pages, routes, TypeScript contracts, package assets, or host-published assets were changed.

No `php artisan x-change:install --force` was run because the asset drift guard was already clean.

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

## Next Recommended Checkpoint

Cockpit Mutation Wave 3N — Durable Activity Closure / Production Readiness Decision

Recommended scope:

- close the durable activity storage mini-wave
- summarize runtime opt-in requirements
- decide whether to enable durable activity in local host config for manual testing
- decide whether the next wave should be journal/action/feedback handoff implementation or broader Cockpit mutation UX
