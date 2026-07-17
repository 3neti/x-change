# Money Movement Wave — Accounting Decision Scaffold

Date: 2026-07-17

## Outcome

This slice scaffolds the decision boundary for future reservation/release accounting without changing money movement.

## Added Read-Only Decision Seam

- `MoneyMovementAccountingDecisionContract`
- `DefaultMoneyMovementAccountingDecisionService`
- `MoneyMovementAccountingDecisionData`

The decision read model records:

- current model: `debit_at_issuance`;
- recommended next model: `reservation_release_pending_decision`;
- candidate accounting models;
- required decisions before wallet behavior can change;
- redaction and side-effect flags.

## Candidate Models Captured

1. Keep debit at issuance.
2. Debit at issuance with terminal release on expiry/cancellation.
3. Reserve at issuance and debit at redemption.

## Lifecycle Demonstration

Lifecycle scenario output now includes:

```text
money_movement_decision
```

Human lifecycle output also prints:

```text
Money Movement Decision
```

This is intended to make the money-model decision visible during scenario runs before any wallet mutation behavior changes.

## Cockpit Surface

Cockpit dashboard liability metrics can now include a read-only `Money Movement Model` fact showing that the current model remains debit-at-issuance and reservation/release remains a decision point.

## Boundaries Preserved

- No wallet debit behavior changed.
- No wallet reservation behavior was added.
- No wallet release/refund behavior was added.
- No expiry job was added.
- No cancellation refund was added.
- No provider call behavior changed.
- No voucher lifecycle mutation changed.
- No execution, journal, action, feedback, campaign, or public API behavior changed.

## Next Decision

Before implementing any true money-movement change, decide whether the target model is:

- keep debit-at-issuance;
- debit-at-issuance with terminal release;
- reservation-at-issuance with debit-at-redemption.
