# Report 015 — Durable Handoff Evidence Source Selection

## Slice

Execution Integration Slice 8 — Durable handoff evidence source selection.

## Decision

The selected first durable source for exact action/feedback handoff evidence is:

```text
post_pipeline_summary_journal_event
```

Target future event type:

```text
execution.handoff.summary.recorded
```

## Rationale

Cockpit already reads execution activity from x-journal. A post-pipeline summary journal event can record the completed handoff profile after journal, action, feedback, and Cockpit activity handoffs have all run.

This source is preferable for the first durable evidence path because it:

- keeps Cockpit read-only
- uses x-journal as the system log
- avoids making x-action or x-feedback lifecycle truth owners
- preserves action as workflow continuation planning
- preserves feedback as communication planning/delivery state
- avoids creating a new table before the evidence contract is proven

## Implemented

Added config-backed source selection:

```text
x-change.execution_result_handoffs.durable_evidence_source
x-change.execution_result_handoffs.durable_evidence_event_type
```

Defaults:

```text
durable_evidence_source = post_pipeline_summary_journal_event
durable_evidence_event_type = execution.handoff.summary.recorded
```

Cockpit execution activity durable evidence now exposes the selected source as:

```text
selected_source.status = selected_not_implemented
selected_source.writes_now = false
selected_source.read_only = true
```

## Boundary

This slice does not write the new post-pipeline summary event.

It does not:

- add a new journal writer
- persist action handoff evidence
- persist feedback handoff evidence
- execute actions
- dispatch feedback
- add Cockpit mutations
- call providers
- mutate vouchers
- move money

## Next Runtime Shape

The next implementation slice should add a non-blocking post-pipeline summary handoff writer that records:

```text
execution_id
voucher_code
correlation_id
target statuses
active targets
performed side-effect targets
failed targets
safe action evidence
safe feedback evidence
redaction metadata
```

That event should be emitted only after all handoff targets have completed.

## Tests

Focused tests:

```bash
php -d memory_limit=1G vendor/bin/pest \
  tests/Feature/Cockpit/CockpitExecutionActivityProjectionTest.php \
  tests/Feature/Console/LifecycleExecutionResultCombinedHandoffProfileTest.php
```

## Next Recommended Slice

Execution Integration Slice 9 — Post-pipeline handoff summary journal event contract.

That slice should define the event payload contract and writer boundary, still non-blocking and without Cockpit mutation.
