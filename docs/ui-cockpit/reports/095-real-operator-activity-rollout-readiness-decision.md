# Cockpit Mutation Wave 5A — Real Operator Activity Rollout Readiness Decision

Status: Decision recorded

Date: 2026-07-11

## Scope

This checkpoint decides what should happen after the seeded diagnostic fixture was visually confirmed in Cockpit.

This is a readiness and rollout decision only. It does not change runtime behavior, frontend UI, routes, controllers, public APIs, repository bindings, recorder bindings, journal handoff bindings, action handoff bindings, feedback handoff bindings, provider behavior, wallet behavior, voucher behavior, or money movement.

## Inputs

The decision is based on the completed Cockpit mutation checkpoints:

- Wave 2B added the operator issuance activity recorder boundary.
- Wave 3I added the database durable activity recorder as an opt-in implementation.
- Wave 3N closed durable activity storage as production-shaped but not production-enabled by default.
- Wave 4R confirmed the populated local diagnostic fixture is visible to the operator in Cockpit.

## Decision

Decision:

```text
Proceed to local real Quick Generate durable activity opt-in verification.
```

Do not enable durable activity recording by default in production yet.

Do not authorize new mutation types beyond the existing Cockpit Quick Generate issuance path.

Do not authorize journal write-side production behavior, action execution, feedback delivery, provider calls outside existing issuance, direct wallet mutation, retry execution, lifecycle truth ownership, raw payload exposure, or money movement outside the existing issuance flow.

## Rationale

The seeded fixture proved the read path:

```text
durable activity row
    ↓
Cockpit read model
    ↓
Operator Issuance Activity presentation
    ↓
safe journal handoff diagnostic display
```

The next risk to reduce is not visual rendering. The next risk is whether real successful Cockpit Quick Generate issuance can record a safe durable operator activity row through the existing opt-in recorder and then hydrate the same read model.

This should be verified locally before any production default is considered.

## Authorized Next Scope

The next checkpoint may:

- enable the existing database durable activity recorder locally;
- keep the existing database durable activity repository locally enabled;
- run one real Cockpit Quick Generate issuance through the existing Cockpit route;
- confirm a durable operator issuance activity row is recorded for the authenticated operator;
- confirm the dashboard renders the real generated Pay Code activity;
- confirm the persisted record contains only operator-safe context and hashed idempotency data;
- confirm journal/action/feedback handoffs remain safe and explicit.

## Explicit Non-Authorization

The next checkpoint must not add:

- new Quick Generate mutation semantics;
- new issuance semantics;
- new voucher execution behavior;
- default production durable activity recording;
- public production config changes;
- journal write-side production defaulting;
- action execution;
- feedback delivery;
- provider calls outside existing Quick Generate issuance;
- direct wallet access;
- direct wallet mutation;
- lifecycle truth ownership;
- raw payload persistence;
- raw provider payload exposure;
- retry execution;
- new mutation controls;
- campaign mutation;
- money movement outside existing issuance.

## Readiness Matrix

| Concern | Current Status | Decision |
| --- | --- | --- |
| Cockpit activity UI renders | Passed with human evidence in Wave 4R | Sufficient for fixture path |
| Durable activity table/model/repository | Implemented as opt-in baseline | Sufficient for local verification |
| Database recorder | Implemented as opt-in baseline | May be enabled locally for verification |
| Repository config | Enabled locally through `.env` | Keep local only |
| Recorder config | Not yet enabled locally | Enable only in the next checkpoint if needed |
| Journal handoff | Diagnostic/read-model evidence only | Do not production-default writes |
| Action handoff | `not_wired` / `none` | Do not execute actions |
| Feedback handoff | `not_wired` | Do not send feedback |
| Mutation scope | Existing Quick Generate only | Do not add new mutations |
| Production readiness | Not ready for default enablement | Require later decision |

## Local Configuration Target for Next Checkpoint

The next checkpoint may use local `.env` entries equivalent to:

```env
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_RECORDER=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder
```

These local entries must not be committed.

## Required Verification for Next Checkpoint

Before accepting real activity rollout locally, verify:

- config resolves to the database repository and database recorder;
- `php artisan x-change:doctor --assets --json` reports no Cockpit asset drift;
- one real Quick Generate issuance succeeds through the existing Cockpit UI or route;
- a durable activity row exists for the authenticated operator;
- the activity row uses a generated Pay Code, not `PC-LOCAL-DIAGNOSTIC`;
- the dashboard renders the generated Pay Code activity;
- raw idempotency key is not persisted;
- raw payloads, provider payloads, wallet data, secrets, tokens, credentials, OTPs, and recipient secrets are not visible;
- action and feedback remain non-executing unless separately authorized;
- any journal handoff behavior remains explicit and safe.

## Rollback

If local real activity recording causes confusion or defects, remove or unset only the local recorder config:

```env
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_RECORDER
```

Then clear config:

```text
php artisan config:clear
```

The dashboard can continue rendering existing durable rows through the local repository config, or the repository config can also be unset for the safe null/not-wired state.

## Boundary Confirmation

This checkpoint did not add:

- source behavior changes;
- frontend changes;
- host-published asset changes;
- `.env` commits;
- route changes;
- controller changes;
- API changes;
- migrations;
- model changes;
- repository changes;
- recorder changes;
- x-journal calls;
- journal writes;
- action execution;
- feedback delivery;
- provider calls;
- wallet access;
- voucher mutation;
- lifecycle truth ownership;
- raw payload exposure;
- retry controls;
- mutation controls;
- money movement.

## Next Recommended Checkpoint

```text
Cockpit Mutation Wave 5B — Real Quick Generate Durable Activity Local Opt-In Verification
```

Recommended scope:

- enable the database recorder locally;
- clear config;
- verify config;
- perform one real Quick Generate issuance;
- confirm the generated Pay Code activity is durably recorded and visible in Cockpit;
- keep all changes local/config-only unless a defect requires a separate tested fix.
