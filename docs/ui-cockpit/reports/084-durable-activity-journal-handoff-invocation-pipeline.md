# Cockpit Mutation Wave 4H — Durable Activity Journal Handoff Invocation Pipeline

Status: Implemented

Date: 2026-07-10

## Scope

This checkpoint wires the existing Quick Generate operator issuance activity path through a package-local handoff pipeline.

The pipeline can record durable activity, invoke the configured journal handoff, and project journal handoff status. The default runtime remains safe because the default recorder, journal handoff, and status projector are still null/not-wired implementations unless explicitly configured.

## Implemented

- Added `CockpitOperatorIssuanceActivityHandoffPipeline`.
- Updated `CockpitQuickGenerateMutationRouteShellController` to call `processOperatorIssuanceActivity`.
- The pipeline will record durable activity first.
- The pipeline will invoke configured journal handoff after activity recording.
- The pipeline will project journal handoff status after handoff result creation.
- Handoff invocation failures are converted into `failed_non_blocking` status projection results.
- Status projector failures are swallowed so they do not block the existing issuance response.

## Runtime Behavior

Fresh Quick Generate mutations now follow this internal sequence:

```text
GeneratePayCode
    ↓
CockpitOperatorIssuanceActivityItemData
    ↓
CockpitOperatorIssuanceActivityHandoffPipeline
    ↓
record durable activity first
    ↓
invoke configured journal handoff
    ↓
project journal handoff status
```

idempotency replays do not invoke handoff again because replayed responses return before activity processing.

## Boundary

This slice does not make journal writes the default.

Hosts must still explicitly configure non-null implementations for durable recording, journal handoff, and status projection before any persistent handoff behavior occurs.

## Non-Goals

- No default journal writes.
- No default durable status persistence.
- No queue job.
- No retry orchestration.
- No action execution.
- No feedback delivery.
- No provider call.
- No wallet access.
- No voucher execution change.
- No lifecycle truth ownership.
- No raw payload exposure.
- No UI changes.
- No mutation controls beyond the existing Quick Generate mutation route.
- No money movement beyond the existing `GeneratePayCode` path.

## Tests

- `tests/Feature/Cockpit/CockpitOperatorIssuanceActivityJournalHandoffInvocationPipelineTest.php`
- `tests/Unit/Architecture/CockpitDurableActivityJournalHandoffInvocationPipelineTest.php`

## Next checkpoint

Cockpit Mutation Wave 4I — Durable Activity Journal Handoff Read Model Exposure.

Recommended scope:

- expose persisted journal handoff status and safe handoff summary through the existing operator issuance activity read model;
- keep the UI read-only;
- do not add retry buttons or new mutation controls;
- do not expose raw journal/provider payloads.
