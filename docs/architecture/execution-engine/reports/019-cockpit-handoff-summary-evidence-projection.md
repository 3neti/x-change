# Execution Integration Slice 12 — Cockpit Handoff Summary Evidence Projection

## Status

Complete.

## Scope

Project durable `execution.handoff.summary.recorded` x-journal evidence into Cockpit execution activity read models.

## Implemented

- Cockpit execution activity queries now read correlated x-journal execution events instead of only `execution.result.recorded`.
- Dashboard execution activity rows still render only `execution.result.recorded` rows.
- When a matching `execution.handoff.summary.recorded` event exists, Cockpit projects exact durable handoff statuses from that summary event.
- Projected durable statuses include:
  - `journal = recorded`
  - `action = composed`
  - `feedback = planned`
  - `handoff_summary_journal = recorded`
- The projection records the source as `x-journal.execution.handoff.summary.recorded`.
- The projection remains read-only and exposes reference-number evidence, not raw journal internals.

## Boundaries Preserved

- Cockpit does not execute actions.
- Cockpit does not send feedback.
- Cockpit does not write journal entries.
- Cockpit does not call providers.
- Cockpit does not mutate vouchers.
- Cockpit does not move money.
- If no summary event exists, Cockpit keeps the previous runtime-config-only projection behavior.

## Verification

Focused test command:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitExecutionActivityProjectionTest.php
```

Result:

```text
4 passed, 77 assertions
```

## Next Recommended Slice

Execution Integration Slice 13 — Lifecycle scenario reporting profile for durable summary projection, including CLI/JSON confirmation that Cockpit can now consume the summary event as durable evidence.
