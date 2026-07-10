# Cockpit Mutation Wave 4J — Durable Activity Journal Handoff Operator Diagnostics

Status: Implemented

Date: 2026-07-10

## Scope

This checkpoint adds read-only operator diagnostics for durable activity journal handoff evidence.

The diagnostics classify already-projected journal handoff status for display. They do not retry handoffs, write journal entries, enqueue work, execute actions, send feedback, or expose raw payloads.

## Implemented

- Added `CockpitOperatorIssuanceActivityJournalHandoffDiagnostics`.
- Classified safe journal handoff evidence into operator-facing states:
  - `recorded`;
  - `not_wired`;
  - `failed_non_blocking`;
  - `unknown`.
- Added safe diagnostic metadata under `metadata.journal_handoff.diagnostic`.
- Updated `CockpitOperatorIssuanceActivityPanel` to render the diagnostic label, description, recommended operator action, and read-only status.
- Updated Cockpit TypeScript contracts for the diagnostic shape.

## Diagnostic Safety Contract

Every diagnostic is:

```text
read_only: true
retry_enabled: false
mutation_enabled: false
raw_payloads_exposed: false
```

Operator diagnostics are display facts only. They are not workflow authority, retry permission, or journal truth.

## Boundary

This slice does not:

- invoke x-journal;
- write to x-journal;
- retry journal handoff;
- create queue jobs;
- expose raw journal payloads;
- expose provider payloads;
- expose wallet data;
- execute actions;
- send feedback;
- move money;
- own lifecycle truth.

## UI Change

Yes. The existing Cockpit dashboard operator issuance activity card can now show a read-only `Operator diagnostic` section inside the journal handoff evidence box when safe diagnostic metadata exists.

No buttons, retry controls, mutation controls, or workflow actions were added.

## Tests

- `tests/Unit/Cockpit/CockpitOperatorIssuanceActivityJournalHandoffDiagnosticsTest.php`
- `tests/Unit/Cockpit/CockpitOperatorIssuanceActivityPresentationTest.php`
- `tests/Feature/Cockpit/CockpitOperatorIssuanceActivityDurableReadModelAdapterTest.php`
- `tests/frontend/cockpit/CockpitDashboardHydration.test.ts`
- `tests/Unit/Architecture/CockpitDurableActivityJournalHandoffOperatorDiagnosticsTest.php`

## Next checkpoint

Cockpit Mutation Wave 4K — Durable Activity Journal Handoff Operator Diagnostics Host Publish / Verification.

Recommended scope:

- run the Cockpit asset drift guard;
- publish package Cockpit assets if stale;
- verify frontend tests and host build;
- keep the slice verification-only unless drift requires publishing.

