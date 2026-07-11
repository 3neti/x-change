# Cockpit Mutation Wave 5M — Local BrickMath Diagnostic Activity Cleanup

Status: Completed locally

Date: 2026-07-11

## Scope

This checkpoint removes the local real-but-diagnostic `YEZA` durable activity row created during Brick\Math characterization.

This checkpoint changes local database state only. It does not change source behavior, Cockpit UI code, host-published assets, local `.env`, routes, controllers, APIs, migrations, models, repositories, recorders, durable activity defaults, journal writes, action execution, feedback delivery, provider calls, direct wallet mutation, voucher execution behavior, campaign mutation, or money movement behavior.

## Decision

Decision:

```text
Remove YEZA / corr-cockpit-brickmath-5f from local durable activity storage.
```

Rationale:

- `YEZA` was created as part of Brick\Math diagnostic characterization.
- `MCPC` remains the intended real Quick Generate activity for UI review.
- Removing `YEZA` keeps the Operator Issuance Activity panel focused for manual review.

## Row Removed

Before deletion:

```text
activity_id: 798f26e301a143a30abd73ea33147ce7c4f651f4bdd867a79197f074b0fa2a17
subject_reference: YEZA
source: cockpit.quick-generate
actor_id: 5
correlation_id: corr-cockpit-brickmath-5f
```

## Command Executed

```bash
XDG_CONFIG_HOME=/private/tmp php artisan tinker --execute 'DB::table("x_change_cockpit_operator_issuance_activities")->where("subject_reference", "YEZA")->where("correlation_id", "corr-cockpit-brickmath-5f")->delete();'
```

## Verification

YEZA verification:

```text
yeza_count: 0
```

MCPC verification:

```text
mcpc_count: 1
```

## Expected UI Effect

The Cockpit Operator Issuance Activity panel should no longer show `Pay Code YEZA issued`.

The panel should continue showing the real `Pay Code MCPC issued` activity while local durable activity repository/recorder config remains enabled.

## Boundary Confirmation

This checkpoint did not add:

- source behavior changes;
- Cockpit UI code changes;
- committed `.env` changes;
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
- direct wallet access changes;
- direct wallet mutation changes;
- lifecycle truth ownership;
- raw payload exposure;
- retry controls;
- new mutation controls;
- campaign mutation;
- money movement behavior changes.

## Next Recommended Step

```text
Manual UI Review — Cockpit Operator Issuance Activity
```

Verify:

- `MCPC` is visible;
- `YEZA` is absent;
- `PC-LOCAL-DIAGNOSTIC` is absent;
- journal/action/feedback remain not wired;
- no raw payloads, secrets, retry controls, or new mutation controls are visible.
