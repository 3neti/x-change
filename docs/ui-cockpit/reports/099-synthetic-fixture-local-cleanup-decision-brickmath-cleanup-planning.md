# Cockpit Mutation Wave 5E — Synthetic Fixture Local Cleanup Decision / BrickMath Cleanup Planning

Status: Decision recorded

Date: 2026-07-11

## Scope

This checkpoint decides the next cleanup posture after the real durable activity path was verified locally and visually accepted.

It also captures the first planning notes for the Brick\Math float deprecation observed during the real Quick Generate verification.

This is a decision and planning checkpoint only. It does not change source behavior, frontend behavior, local `.env`, database rows, routes, controllers, APIs, migrations, models, repositories, recorders, provider behavior, wallet behavior, voucher behavior, journal behavior, action behavior, feedback behavior, or money movement.

## Decision 1 — Synthetic Fixture Cleanup

Decision:

```text
Do not remove the `PC-LOCAL-DIAGNOSTIC` synthetic fixture row in this checkpoint.
```

Rationale:

- The fixture is no longer required for the primary real-activity proof because `MCPC` now proves the real durable activity path.
- The fixture still provides useful side-by-side visual comparison between:
  - real durable activity with `journal: not_wired`;
  - synthetic diagnostic activity with `journal: recorded`.
- Removing a local database row is a local-state mutation and should remain explicit.

Result:

```text
Keep `PC-LOCAL-DIAGNOSTIC` for now.
```

Future cleanup may remove only the synthetic fixture row after explicit approval.

## Future Synthetic Fixture Removal Procedure

If approved later, remove only:

```text
activity_id: fixture-cockpit-journal-diagnostic-activity
subject_reference: PC-LOCAL-DIAGNOSTIC
source: cockpit.local-diagnostic-fixture
```

Do not delete:

```text
subject_reference: MCPC
source: cockpit.quick-generate
```

Recommended verification before deletion:

```sql
SELECT activity_id, actor_id, source, subject_reference, journal_handoff_status
FROM x_change_cockpit_operator_issuance_activities
WHERE subject_reference IN ('PC-LOCAL-DIAGNOSTIC', 'MCPC');
```

Recommended deletion shape if explicitly approved:

```sql
DELETE FROM x_change_cockpit_operator_issuance_activities
WHERE activity_id = 'fixture-cockpit-journal-diagnostic-activity'
  AND subject_reference = 'PC-LOCAL-DIAGNOSTIC'
  AND source = 'cockpit.local-diagnostic-fixture';
```

This checkpoint does not execute that deletion.

## Decision 2 — Real Durable Activity Row

Decision:

```text
Keep `MCPC` in the local database.
```

Rationale:

- `MCPC` is the real local proof generated through the existing Quick Generate path.
- It remains useful for continued Cockpit dashboard verification.
- It contains only safe durable activity evidence.

## Decision 3 — Local Recorder Configuration

Decision:

```text
Keep the local database repository and recorder enabled for manual testing.
```

Current local-only config may remain:

```env
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_RECORDER=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder
```

These values must remain uncommitted.

## Decision 4 — Production Default

Decision:

```text
Do not production-enable durable activity recording by default.
```

Still required before production default enablement:

- retention and purge policy;
- operator authorization and tenant scoping;
- production observability for recorder failures;
- journal write-side decision;
- action continuation decision;
- feedback intent decision;
- dashboard filtering/search for activity history;
- rollout and rollback procedure.

## Brick\Math Float Deprecation Planning

Observed warning during Wave 5B:

```text
Passing floats to BigNumber::of() and arithmetic methods is deprecated and will be removed in 0.15.
```

Initial source inspection found candidate areas to characterize before changing behavior:

```text
GeneratePayCode::requiredIssuanceAmount()
    returns float from cash.amount + estimate.total

EstimatePayCodeCost::handle()
    casts pricing estimate values to float

InstructionRevenueAllocatorService::allocate()
    derives amount from minor units and calls transferFloat()

InstructionRevenueAllocatorService::buildTransferMeta()
    stores price as float in metadata

VoucherIssuancePayloadNormalizer
    preserves incoming cash.amount shapes except for collectible flow
```

Recommended cleanup slice:

```text
Cockpit / issuance cleanup — Monetary String Normalization for BrickMath Compatibility
```

Recommended cleanup test scope:

- characterize current Quick Generate issuance with integer, string decimal, and float amounts;
- assert generated voucher amount, activity amount, funding amount, and pricing totals remain unchanged;
- assert the real Quick Generate route does not emit the Brick\Math float deprecation warning;
- verify wallet debit/allocation behavior is unchanged;
- verify generated Pay Code response redaction remains unchanged;
- verify durable activity recording still stores amount as operator-safe display context.

Recommended implementation direction:

- prefer string decimal values at boundaries that eventually reach wallet / Brick\Math arithmetic;
- avoid changing public request semantics;
- avoid changing generated voucher semantics;
- avoid changing wallet movement behavior;
- avoid replacing provider funding policy behavior.

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
Cockpit Mutation Wave 5F — BrickMath Monetary Normalization Characterization
```

Recommended scope:

- add failing/characterization coverage around the observed Brick\Math deprecation;
- identify the exact call path emitting the warning;
- fix monetary normalization only if the test proves the safe boundary;
- preserve Quick Generate behavior, voucher behavior, wallet behavior, durable activity behavior, and redaction behavior.
