# Money Movement Wave — Target Model Approval Gate

Date: 2026-07-17

## Outcome

This slice records the recommended future accounting model without selecting or activating it.

## Added Read-Only Target Model Seam

- `MoneyMovementTargetModelContract`
- `DefaultMoneyMovementTargetModelService`
- `MoneyMovementTargetModelData`

## Current Position

- Current model: `debit_at_issuance`
- Recommended model: `reserve_at_issuance_debit_at_redemption`
- Selected model: none
- Status: `pending_human_approval`

## Approval Requirements

- Approve wallet-owned reservation ledger semantics.
- Approve x-change-owned Pay Code lifecycle trigger mapping.
- Approve idempotency keys for reservation, capture, release, and reversal events.
- Approve migration posture for existing debit-at-issuance Pay Codes.
- Approve operator-facing labels for ledger truth versus operational estimates.

## Lifecycle Demonstration

Lifecycle output now includes:

```text
money_movement_target
```

Human lifecycle output also renders:

```text
Money Movement Target
```

## Boundaries Preserved

- No target model was selected.
- No wallet reservation behavior was added.
- No wallet release/refund behavior was added.
- No wallet debit behavior changed.
- No expiry or cancellation money movement was added.
- No provider, execution, journal, action, feedback, campaign, voucher lifecycle, or public API behavior changed.
