# Money Movement Wave — Lifecycle Trigger Matrix

Date: 2026-07-17

## Outcome

This slice maps the lifecycle events that would eventually request reservation, capture, release, or reversal behavior.

The matrix is read-only and planning-only.

## Added Read-Only Trigger Matrix Seam

- `MoneyMovementLifecycleTriggerMatrixContract`
- `DefaultMoneyMovementLifecycleTriggerMatrixService`
- `MoneyMovementLifecycleTriggerMatrixData`

## Planned Triggers

- `pay_code_issued`
- `pay_code_redeemed`
- `pay_code_partially_claimed`
- `pay_code_expired`
- `pay_code_cancelled`
- `provider_disbursement_failed_after_capture`

Every trigger is currently disabled.

## Ownership Direction

The matrix records the expected ownership split:

- x-change requests lifecycle-driven money operations.
- wallet records durable reservation, capture, release, and reversal ledger entries.

This remains an architectural direction, not an implemented money-movement behavior.

## Lifecycle Demonstration

Lifecycle output now includes:

```text
money_movement_triggers
```

Human lifecycle output also renders:

```text
Money Movement Triggers
```

## Boundaries Preserved

- No wallet reservation behavior was added.
- No capture behavior was added.
- No release/refund behavior was added.
- No reversal behavior was added.
- No wallet debit behavior changed.
- No expiry job or cancellation release was added.
- No provider, execution, journal, action, feedback, campaign, voucher lifecycle, or public API behavior changed.
