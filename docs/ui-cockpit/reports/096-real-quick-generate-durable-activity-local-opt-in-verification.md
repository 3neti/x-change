# Cockpit Mutation Wave 5B — Real Quick Generate Durable Activity Local Opt-In Verification

Status: Verified locally

Date: 2026-07-11

## Scope

This checkpoint locally verifies that a real Cockpit Quick Generate issuance can be recorded through the existing opt-in durable operator issuance activity recorder and then hydrated through the Cockpit read model.

This checkpoint does not enable durable activity recording by default in production.

The local `.env` change is intentionally not committed.

## Local Configuration Enabled

The local host now has both durable activity read and record seams enabled:

```env
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository
XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_RECORDER=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder
```

Configuration cache was cleared:

```text
php artisan config:clear
```

Result:

```text
Configuration cache cleared successfully.
```

## Configuration Verification

Repository config:

```text
php artisan config:show x-change.cockpit.operator_issuance_activity.repository
```

Result:

```text
x-change.cockpit.operator_issuance_activity.repository  LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository
```

Recorder config:

```text
php artisan config:show x-change.cockpit.operator_issuance_activity.recorder
```

Result:

```text
x-change.cockpit.operator_issuance_activity.recorder  LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder
```

## Asset Drift Verification

Command:

```text
php artisan x-change:doctor --assets --json
```

Result:

```text
success: true
checked: 55
ok: 55
stale: 0
missing: 0
extra: 0
```

## Real Quick Generate Verification

One local real Quick Generate request was sent through the existing route:

```text
POST /x/cockpit/quick-generate
```

Authenticated operator:

```text
id: 5
email: admin@disburse.cash
```

Request identity:

```text
Idempotency-Key: cockpit-real-activity-5b-20260711
X-Correlation-ID: corr-cockpit-real-activity-5b
amount: PHP 25
count: 1
```

Result:

```text
HTTP 201
status: issued
result.code: MCPC
result.amount: 25
result.currency: PHP
result.links.cockpit_detail: /x/cockpit/pay-codes/MCPC
```

Runtime note:

```text
Laravel emitted an existing Brick\Math deprecation warning about passing floats to BigNumber::of().
```

The warning did not block issuance or durable activity recording. It should be tracked separately as a technical cleanup item and not conflated with the Cockpit durable activity verification.

## Durable Activity Database Verification

The durable activity table contains the generated Pay Code activity:

```text
activity_id: d7f44051cdcaa50f646e5bb8bc186a53372602be5fedaa53010061a1fa5da10a
actor_id: 5
source: cockpit.quick-generate
subject_reference: MCPC
status: issued
idempotency_key_hash: 1abaf37676d8abbaab5b8cc827c83ce5eb546759fb5bea9bf1865da62dc231f7
correlation_id: corr-cockpit-real-activity-5b
journal_handoff_status: not_wired
action_handoff_status: not_wired
feedback_handoff_status: not_wired
```

Safe context:

```json
{
  "code": "MCPC",
  "amount": "25",
  "currency": "PHP",
  "route": "x-change.cockpit.quick-generate.store",
  "detail_href": "/x/cockpit/pay-codes/MCPC"
}
```

Metadata:

```json
{
  "source": "x-change.cockpit",
  "presentation_only": true,
  "recorder": "cockpit.operator-issuance-activity.v1"
}
```

Redaction flags:

```json
{
  "raw_payloads_exposed": false,
  "provider_payloads_exposed": false,
  "wallet_data_exposed": false,
  "recipient_secrets_exposed": false
}
```

The raw idempotency key was not persisted. Only `idempotency_key_hash` is stored.

## Cockpit Read Model Verification

The Cockpit read model hydrates the real generated activity for operator `5`:

```text
status: available
authorized: true
source: durable-operator-issuance-activity-read-model
count: 1
first_code: MCPC
first_title: Pay Code MCPC issued
```

Presentation:

```text
schema: x-change.cockpit.operator-issuance-activity-presentation.v1
code: MCPC
title: Pay Code MCPC issued
subtitle: PHP 25 issued through Quick Generate
status: issued
detail_href: /x/cockpit/pay-codes/MCPC
correlation_id: corr-cockpit-real-activity-5b
journal: not_wired
action: not_wired
feedback: not_wired
presentation_only: true
writes_journal: false
executes_actions: false
sends_feedback: false
moves_money: false
owns_lifecycle_truth: false
```

Journal diagnostic metadata remains read-only:

```text
classification: not_wired
label: Journal handoff not wired
operator_action: configure_when_ready
read_only: true
retry_enabled: false
mutation_enabled: false
raw_payloads_exposed: false
```

No `raw_payload`, `provider_payload`, or `wallet` metadata was exposed in the presentation.

## Verification Outcome

Result:

```text
Pass — local real Quick Generate durable activity opt-in verified.
```

This proves:

- the existing Quick Generate mutation path can record real operator activity when the recorder is explicitly enabled locally;
- the generated Pay Code activity is durably recorded for the authenticated operator;
- the Cockpit read model can hydrate the generated Pay Code activity;
- journal/action/feedback handoff statuses remain explicit and non-executing;
- unsafe payloads remain excluded from durable activity presentation.

## Boundary Confirmation

This checkpoint did not add:

- committed `.env` changes;
- production default durable activity recording;
- new runtime behavior;
- new frontend behavior;
- host-published asset changes;
- route changes;
- controller changes;
- API changes;
- migrations;
- model changes;
- repository changes;
- recorder changes;
- new Quick Generate semantics;
- voucher execution changes;
- journal writes;
- action execution;
- feedback delivery;
- provider calls outside the existing Quick Generate issuance path;
- direct wallet access;
- direct wallet mutation;
- lifecycle truth ownership;
- raw payload exposure;
- retry controls;
- mutation controls;
- campaign mutation.

This checkpoint did perform one real local Quick Generate issuance for `PHP 25`, producing Pay Code `MCPC`, through the existing authorized issuance path.

## Remaining Risk

The real issuance surfaced an existing deprecation warning:

```text
Passing floats to BigNumber::of() and arithmetic methods is deprecated and will be removed in 0.15.
```

Recommended handling:

- create a separate cleanup slice to normalize affected monetary values to strings before Brick\Math receives them;
- do not mix that cleanup with Cockpit durable activity rollout.

## Next Recommended Checkpoint

```text
Cockpit Mutation Wave 5C — Real Durable Activity Human Visual Confirmation
```

Recommended scope:

- have the human reviewer open `/x/cockpit`;
- confirm `Pay Code MCPC issued` appears in Operator Issuance Activity;
- confirm journal/action/feedback remain `not_wired`;
- confirm no unsafe payloads, retry controls, or new mutation controls are visible;
- record pass/block/fail evidence.
