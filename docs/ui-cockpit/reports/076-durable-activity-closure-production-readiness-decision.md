# Cockpit Mutation Wave 3N — Durable Activity Closure / Production Readiness Decision

Status: Implemented

Date: 2026-07-10

## Decision

The durable operator issuance activity mini-wave is closed as an opt-in baseline.

Durable activity storage is production-shaped but not production-enabled by default.

## Completed Baseline

- Database schema exists for operator-safe activity records.
- Eloquent model exists for the durable activity table.
- Database repository exists and applies redaction/retention before persistence.
- Database recorder exists and hashes raw idempotency keys before repository handoff.
- Runtime config seams exist for explicit repository and recorder opt-in.
- Durable read-model adapter exists and hydrates existing dashboard props.
- Dashboard props were verified in both default not-wired and configured durable states.
- Host-published Cockpit assets were verified clean.

## Runtime Opt-In Requirements

To enable durable activity storage, configure both:

```php
'x-change.cockpit.operator_issuance_activity.repository'
    => LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository::class,

'x-change.cockpit.operator_issuance_activity.recorder'
    => LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder::class,
```

The package config also exposes these classes under:

```php
x-change.cockpit.operator_issuance_activity.available_repositories.database
x-change.cockpit.operator_issuance_activity.available_recorders.database
```

## Current Safety Boundary

This baseline records only operator-safe Quick Generate activity evidence.

It does not:

- write journal entries
- execute x-action actions
- send x-feedback deliveries
- call providers outside the existing Quick Generate issuance path
- access wallets directly
- mutate vouchers outside existing issuance
- own lifecycle truth
- expose raw payloads
- move money outside existing issuance

## Production Readiness Decision

Ready for local/manual opt-in testing.

Not yet ready to enable by default in production.

Before production default enablement, decide:

1. retention period and purge process
2. operator authorization/tenant scoping policy
3. whether activity should also hand off to x-journal
4. whether activity should create x-action continuation hints
5. whether activity should trigger x-feedback intents
6. whether Cockpit should expose operator filters/search for durable activity

## Next Recommended Wave

Cockpit Mutation Wave 4 — Journal / Action / Feedback Handoff Implementations

Recommended first checkpoint:

```text
Cockpit Mutation Wave 4A — Durable Activity Journal Handoff Implementation Decision
```

Recommended scope:

- decide whether Quick Generate durable activity should append x-journal entries
- keep journal handoff opt-in
- preserve durable activity as operational evidence, not lifecycle truth
- do not add action execution or feedback delivery yet
