# Cockpit Mutation Wave 5D — Durable Activity Local Opt-In Closure / Cleanup Decision

Status: Decision recorded

Date: 2026-07-11

## Scope

This checkpoint closes the local durable operator issuance activity opt-in validation loop after:

- Wave 5B verified real Quick Generate durable activity recording locally;
- Wave 5C confirmed the real durable activity renders in Cockpit by human visual inspection.

This is a decision-only checkpoint. It does not change source behavior, frontend behavior, local `.env`, database rows, routes, controllers, APIs, migrations, models, repositories, recorders, provider behavior, wallet behavior, voucher behavior, journal behavior, action behavior, feedback behavior, or money movement.

## Closure Decisions

### 1. Local Recorder Configuration

Decision:

```text
Keep the local database repository and recorder enabled for continued manual Cockpit testing.
```

Rationale:

- The local opt-in path has been verified end-to-end.
- Keeping it enabled locally lets future manual Quick Generate tests immediately produce operator activity evidence.
- The setting remains local-only through ignored `.env` values.

Boundary:

```text
Do not enable durable activity recording by default in production yet.
```

### 2. Synthetic Diagnostic Fixture

Decision:

```text
The synthetic diagnostic fixture is no longer required for the primary real-activity proof, but should not be removed inside this decision checkpoint.
```

Rationale:

- `PC-LOCAL-DIAGNOSTIC` was useful to verify journal diagnostic rendering before real durable activity existed.
- Real activity `MCPC` now proves the production-shaped local recorder path.
- Removing local database rows is a separate local-state mutation and should be explicit.

Boundary:

```text
Authorize a future local cleanup checkpoint to remove the synthetic fixture if the operator wants the dashboard to show only real activity.
```

### 3. Real Durable Activity Row

Decision:

```text
Keep the real `MCPC` durable activity row in the local database for continued visual verification.
```

Rationale:

- It is real local evidence that the existing Quick Generate path can record durable operator activity.
- It does not expose unsafe payloads.
- It remains useful for future read-model and UI checks.

### 4. Brick\Math Deprecation Warning

Decision:

```text
Track the Brick\Math float deprecation as a separate cleanup slice.
```

Rationale:

- The warning was surfaced during real Quick Generate verification.
- It did not block issuance or durable activity recording.
- It is not a Cockpit activity storage design issue.
- It should be fixed with focused monetary normalization coverage rather than mixed into Cockpit rollout documentation.

Recommended future cleanup:

```text
Cockpit / issuance cleanup — normalize monetary values before Brick\Math receives them.
```

### 5. Production Default Enablement

Decision:

```text
Do not enable durable activity recording by default in production yet.
```

Required before production default enablement:

- explicit retention policy and purge process;
- operator authorization and tenant scoping policy;
- production observability for recorder failures;
- production decision on journal handoff write behavior;
- production decision on action continuation hints;
- production decision on feedback intent generation;
- dashboard filtering/search strategy for durable activity history;
- rollout and rollback procedure.

## Current Local State

The local host may remain configured with:

```env
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_RECORDER=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder
```

These entries remain local-only and must not be committed.

Known local rows:

```text
MCPC
    real Quick Generate durable activity
    journal/action/feedback: not_wired
    safe for continued visual verification

PC-LOCAL-DIAGNOSTIC
    synthetic diagnostic fixture
    journal: recorded fixture metadata
    removable in a future explicit local cleanup checkpoint
```

## Rollback Guidance

To stop recording new local durable activity, unset:

```env
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_RECORDER
```

Then run:

```text
php artisan config:clear
```

To return the dashboard to the safe null/not-wired activity state, also unset:

```env
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY
```

Then run:

```text
php artisan config:clear
```

Do not commit either local config change.

## Boundary Confirmation

This checkpoint did not add:

- committed `.env` changes;
- database writes;
- database deletes;
- source behavior changes;
- frontend behavior changes;
- host-published asset changes;
- route changes;
- controller changes;
- API changes;
- migrations;
- model changes;
- repository changes;
- recorder changes;
- production default durable activity recording;
- new Quick Generate semantics;
- voucher execution changes;
- journal writes;
- action execution;
- feedback delivery;
- provider calls;
- direct wallet access;
- direct wallet mutation;
- lifecycle truth ownership;
- raw payload exposure;
- retry controls;
- new mutation controls;
- campaign mutation;
- money movement.

## Next Recommended Checkpoint

```text
Cockpit Mutation Wave 5E — Synthetic Fixture Local Cleanup Decision / BrickMath Cleanup Planning
```

Recommended scope:

- decide whether to remove the `PC-LOCAL-DIAGNOSTIC` local fixture row now that real activity is visible;
- if approved, remove only the synthetic fixture row from the local database and document the command/query used;
- draft a separate tested cleanup plan for the Brick\Math float deprecation;
- keep durable activity production default enablement deferred.
