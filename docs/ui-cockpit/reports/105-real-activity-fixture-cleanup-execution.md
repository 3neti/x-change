# Wave 5I — Real Activity Fixture Cleanup Decision / Execution

Status: Completed locally

Date: 2026-07-11

## Scope

This checkpoint removed the synthetic local diagnostic fixture row now that a real Quick Generate durable activity row exists for visual verification.

This checkpoint changed local database state only. It did not change source behavior, Cockpit UI code, routes, controllers, APIs, migrations, models, repositories, recorders, durable activity defaults, journal writes, action execution, feedback delivery, provider calls, direct wallet mutation, voucher execution behavior, campaign mutation, or money movement behavior.

## Decision

Decision:

```text
Remove the synthetic PC-LOCAL-DIAGNOSTIC fixture row.
```

Rationale:

- Real durable activity evidence exists for `MCPC`.
- The synthetic fixture was useful for proving populated diagnostic rendering.
- Keeping it longer risks confusing manual UI review because it appears beside real activity.

## Command Executed

```bash
XDG_CONFIG_HOME=/private/tmp php artisan tinker --execute 'DB::table("x_change_cockpit_operator_issuance_activities")->where("activity_id", "fixture-cockpit-journal-diagnostic-activity")->where("subject_reference", "PC-LOCAL-DIAGNOSTIC")->delete();'
```

Initial attempt without `XDG_CONFIG_HOME=/private/tmp` failed because PsySH tried to write history under `/Users/rli/.config/psysh/psysh_history`.

## Verification

Fixture verification:

```sql
select count(*) as fixture_count
from x_change_cockpit_operator_issuance_activities
where activity_id = 'fixture-cockpit-journal-diagnostic-activity'
   or subject_reference = 'PC-LOCAL-DIAGNOSTIC';
```

Result:

```text
fixture_count: 0
```

Real activity verification:

```sql
select activity_id, subject_reference, source, actor_id
from x_change_cockpit_operator_issuance_activities
where subject_reference = 'MCPC'
limit 5;
```

Result:

```text
subject_reference: MCPC
source: cockpit.quick-generate
actor_id: 5
```

## Expected UI Effect

The synthetic `PC-LOCAL-DIAGNOSTIC` diagnostic card should no longer appear in the Cockpit Operator Issuance Activity panel.

The real `MCPC` Quick Generate activity should remain visible while local durable activity repository/recorder configuration remains enabled.

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

## Next Recommended Checkpoint

```text
Wave 5J — Durable Activity Local Opt-In Closure
```

Potential UI effect:

```text
If local durable activity repository/recorder config is disabled, the Operator Issuance Activity panel may return to empty/not-wired.
```
