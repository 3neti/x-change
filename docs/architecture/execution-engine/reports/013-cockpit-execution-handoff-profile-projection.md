# Report 013 — Cockpit Execution Handoff Profile Projection

## Slice

Execution Integration Slice 6 — Cockpit read-model projection for combined execution handoff status.

## Purpose

Expose execution-result handoff status as read-only Cockpit evidence without adding execution, retry, resend, action execution, or feedback delivery behavior.

## Implemented

- Added optional safe metadata to `CockpitDashboardActivityData`.
- Extended x-journal-backed execution activity rows with:

```text
metadata.execution_handoff_profile
```

- The projected profile includes:
  - target statuses
  - active targets
  - side-effecting targets
  - failed targets
  - non-blocking status
  - projection safety metadata

## Projection Semantics

Cockpit receives confirmed execution evidence from x-journal `execution.result.recorded` entries.

For this slice:

- `journal = recorded` is confirmed by the x-journal entry.
- `action = enabled_not_projected` means the x-action execution-result handoff is configured, but no durable action evidence is projected from the journal entry.
- `feedback = enabled_not_projected` means the x-feedback execution-result handoff is configured, but no durable feedback delivery evidence is projected from the journal entry.
- `cockpit_activity = not_wired` remains unchanged.

This avoids overstating action/feedback facts that are not yet persisted as execution evidence.

## Boundary

This slice does not:

- execute voucher drivers
- write journal entries from Cockpit
- execute x-action actions
- send x-feedback deliveries
- retry failed handoffs
- call providers
- mutate vouchers
- move money

## Tests

Focused tests:

```bash
php -d memory_limit=1G vendor/bin/pest \
  tests/Feature/Cockpit/CockpitExecutionActivityProjectionTest.php \
  tests/Feature/Console/LifecycleExecutionResultCombinedHandoffProfileTest.php \
  tests/Unit/Services/ExecutionResultHandoffPipelineTest.php
```

Result:

```text
6 passed, 65 assertions
```

## Next Recommended Slice

Execution Integration Slice 7 — Durable action/feedback handoff evidence projection decision.

That slice should decide whether action and feedback handoff evidence should be persisted into x-journal, durable Cockpit activity, or separate package-owned read models before Cockpit claims exact `composed` / `planned` execution-side statuses.
