# Execution Integration Slice 13 — Lifecycle Durable Summary Projection Reporting

## Status

Completed on 2026-07-15.

## Mission

Make the lifecycle scenario runner explicitly report whether a completed voucher execution has durable post-pipeline summary evidence that Cockpit can consume.

This slice is reporting-only. It does not change voucher execution behavior, provider behavior, journal writes, action execution, feedback delivery, Cockpit mutation, wallet access, or money movement.

## Implementation

Added `execution.projection_profile` to each execution formatted by `ExecutionEngineContractScenarioRunner`.

The profile exposes:

- `schema`
- `status`
- `execution_id`
- `voucher_code`
- `correlation_id`
- target statuses
- projected targets
- side-effect targets
- failed targets
- Cockpit projection source
- summary event type
- summary reference number when available
- read-only/non-mutating projection guarantees

When `handoff_summary_journal` is `recorded`, the profile status is:

```text
durable_summary_evidence_available
```

and the Cockpit projection source is:

```text
x-journal.execution.handoff.summary.recorded
```

Without that durable summary event, the profile remains runtime-only:

```text
runtime_handoff_profile_only
```

## Human CLI Output

The lifecycle command now renders a compact human-readable projection section:

```text
Execution Projection: durable_summary_evidence_available
Cockpit Projection Source: x-journal.execution.handoff.summary.recorded
Projected Targets: journal, action, feedback, handoff_summary_journal
```

## Files Changed

- `src/Lifecycle/Runners/ExecutionEngineContractScenarioRunner.php`
- `src/Console/Commands/Lifecycle/LifecycleResultRenderer.php`
- `tests/Feature/Console/LifecycleExecutionResultHandoffSummaryXJournalWriterTest.php`

## Tests

Focused TDD test was added first and failed because `execution.projection_profile` did not exist.

Then implementation was added and the focused test passed.

## Boundary

This slice only improves lifecycle scenario reporting.

It does not:

- execute actions
- send feedback
- write additional journal entries
- call providers
- mutate vouchers
- access wallets
- move money
- make Cockpit an execution surface

## Next Recommended Slice

Execution Integration Slice 14 — Cockpit execution activity UI surfacing for durable summary projection status.

That slice should decide whether the existing dashboard Recent Activity component needs a visible status/badge change, or whether the current read-model metadata is sufficient until a broader Cockpit activity redesign.
