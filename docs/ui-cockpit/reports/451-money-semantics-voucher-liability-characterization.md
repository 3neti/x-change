# Money Semantics Wave — Voucher Liability and Usable Balance Characterization

Date: 2026-07-17

## Outcome

This slice characterizes current x-change money semantics without changing wallet behavior.

## Current Behavior Captured

- Pay Code generation checks funding and debits/allocates the issuer wallet during issuance.
- Active unredeemed Pay Codes are now summarized as read-only outstanding liability.
- Redeemed, expired, and cancelled Pay Codes are excluded from outstanding liability.
- Expired and cancelled Pay Codes do not currently credit, refund, reserve-release, or otherwise mutate issuer wallet balance.

## Added Read Models

- `VoucherLiabilitySummaryContract`
- `VoucherLiabilitySummaryService`
- `VoucherLiabilitySummaryData`

The summary exposes:

- wallet balance;
- active issued amount;
- redeemed amount;
- expired amount;
- cancelled amount;
- outstanding Pay Code liability;
- usable balance estimate.

## Cockpit Surface

- Header HUD now supports:
  - Internal Balance;
  - Outstanding Pay Codes;
  - Usable Balance;
  - Live Balance.
- Dashboard funding metrics can include active/redeemed/expired/cancelled liability breakdowns.

## Lifecycle Demonstration

New scenario:

```bash
php artisan xchange:lifecycle:run money_semantics_voucher_liability_demo --no-claim --json
```

The JSON output includes:

- `money_semantics.before_issuance`
- `money_semantics.after_issuance`

Normal claim runs also include:

- `money_semantics.after_claim`

## Boundaries Preserved

- No wallet credit/refund/release behavior was added.
- No reservation ledger was added.
- No expiry job was added.
- No cancellation money movement was added.
- No provider call behavior changed.
- No redemption, execution, journal, action, feedback, campaign, or public API behavior changed.

## Next Decision

If the liability numbers are accepted, the next wave should decide whether to introduce a true reservation/release ledger model.
