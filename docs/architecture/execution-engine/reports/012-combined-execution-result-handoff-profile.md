# Report 012 — Combined Execution Result Handoff Profile

## Slice

Execution Integration Slice 5 — Combined execution-result handoff profile / reporting hardening.

## Purpose

Prove that x-change can enable the post-execution journal, action, and feedback handoffs together without changing voucher execution semantics or making any downstream package responsible for execution truth.

## Implemented

- Added aggregate `profile` reporting to `ExecutionResultHandoffSummaryData::toReportArray()`.
- The profile reports:
  - target statuses
  - active targets
  - side-effecting targets
  - failed targets
  - non-blocking status
- Added lifecycle coverage for combined handoffs:
  - `journal = x-journal`
  - `action = x-action`
  - `feedback = x-feedback`
  - `cockpit_activity = null`

## Verified Contract

When all three handoffs are enabled together:

```text
execution.handoffs.profile.targets.journal = recorded
execution.handoffs.profile.targets.action = composed
execution.handoffs.profile.targets.feedback = planned
execution.handoffs.profile.targets.cockpit_activity = not_wired
execution.handoffs.profile.performed_side_effect_targets = [journal]
execution.handoffs.profile.failed_targets = []
execution.handoffs.profile.non_blocking = true
```

## Boundary

This slice does not:

- run inside the voucher execution engine
- change execution result status
- dispatch feedback delivery
- execute x-action actions
- authorize operator actions
- make Cockpit write journal entries
- call providers
- mutate vouchers
- move money

## Architectural Decision

The execution result handoff pipeline remains x-change-owned, post-execution, and non-blocking.

The only handoff in the current combined profile that performs a side effect is x-journal recording. x-action remains presentation/continuation composition only. x-feedback remains prepare-only communication planning.

## Tests

Focused tests:

```bash
php -d memory_limit=1G vendor/bin/pest \
  tests/Unit/Services/ExecutionResultHandoffPipelineTest.php \
  tests/Feature/Console/LifecycleExecutionResultCombinedHandoffProfileTest.php \
  tests/Feature/Console/LifecycleExecutionResultXJournalHandoffTest.php \
  tests/Feature/Console/LifecycleExecutionResultXActionHandoffTest.php \
  tests/Feature/Console/LifecycleExecutionResultXFeedbackHandoffTest.php
```

Result:

```text
7 passed, 71 assertions
```

## Next Recommended Slice

Execution Integration Slice 6 — Cockpit read-model projection for combined execution handoff status.

That slice should expose the combined `journal/action/feedback` handoff profile as read-only Cockpit evidence without enabling retries, resend, action execution, or mutation controls.
