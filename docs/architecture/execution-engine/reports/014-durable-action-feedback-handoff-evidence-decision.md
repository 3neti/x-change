# Report 014 — Durable Action / Feedback Handoff Evidence Projection Decision

## Slice

Execution Integration Slice 7 — Durable action/feedback handoff evidence projection decision.

## Decision

Cockpit must not project exact action or feedback execution handoff statuses from runtime configuration alone.

For x-journal-backed execution activity rows:

- `journal = recorded` is durable and projected.
- `action = enabled_not_projected` may be shown when x-action is configured.
- `feedback = enabled_not_projected` may be shown when x-feedback is configured.
- exact `action = composed` and `feedback = planned` remain unavailable to Cockpit until a durable evidence source exists.

## Rationale

The current execution-result handoff pipeline runs in this order:

```text
execution result
  ↓
journal handoff
  ↓
action handoff
  ↓
feedback handoff
  ↓
cockpit activity handoff
```

The persisted `execution.result.recorded` x-journal entry is created before action and feedback handoffs are evaluated. Therefore that journal entry cannot truthfully contain the final action/feedback handoff results.

Lifecycle scenario JSON can report the full in-memory handoff summary, but Cockpit read models need durable evidence, not transient runtime state.

## Implemented

`metadata.execution_handoff_profile` now includes:

```text
durable_evidence
```

The durable evidence decision identifies:

- whether the target is durable
- which source backs the projection
- why action/feedback remain deferred
- what future source is required before exact statuses can be projected

## Boundary

This slice does not:

- write new journal events
- persist action handoff evidence
- persist feedback handoff evidence
- execute x-action actions
- dispatch x-feedback deliveries
- add Cockpit mutations
- call providers
- mutate vouchers
- move money

## Future Options

Acceptable future durable evidence sources:

1. x-action read model or journal event for composed continuation hints.
2. x-feedback read model or journal event for prepared delivery plans.
3. A dedicated durable execution-handoff evidence record.
4. A post-pipeline summary journal event that occurs after action/feedback planning.

Until one is selected and implemented, Cockpit must keep action/feedback handoff evidence as deferred/configuration-aware only.

## Tests

Focused tests:

```bash
php -d memory_limit=1G vendor/bin/pest \
  tests/Feature/Cockpit/CockpitExecutionActivityProjectionTest.php \
  tests/Unit/Cockpit/CockpitReadModelBaselineTest.php
```

## Next Recommended Slice

Execution Integration Slice 8 — Durable handoff evidence source selection.

That slice should choose the first durable source for action/feedback evidence before any Cockpit UI claims exact action or feedback handoff outcomes.
