# Payment Monitoring Compass

Last updated: 2026-09-23

## North Star

Every live QR Ph tied to a Pay Code is observed automatically throughout its
valid lifetime. Payment discovery remains separate from applying the payment
to a business purpose.

## Paused Position

This workstream is intentionally paused after the bounded testing canary.

| Area | State |
|---|---|
| NetBank transaction parsing | Fixed in `3neti/emi-netbank v2.3.7` |
| Scheduled monitoring | Enabled in the Laravel Cloud testing instance |
| Monitoring batch | Five attempts per scheduled run |
| Immutable observation ledger | Working |
| Scheduler-triggered verification and settlement | Proven with Pay Code `8DPZ` |
| Expiry boundary | No monitoring after expiry plus configured grace |
| Queue backlog at pause | Zero |
| Failed jobs at pause | Zero |
| Duplicate-observation protection | Implemented; concurrent proof remains pending |
| x-PayOut or production enablement | Not started |

## Completed Gates

1. Corrected the NetBank adapter so a confirmed HTTP 200 response with matching
   VCA/account identifiers and no `transactions` member is treated as an empty
   transaction history. Malformed or mismatched responses continue to fail
   closed.
2. Enabled a one-attempt testing canary and proved scheduled payment processing
   with `8DPZ`, including one immutable ₱60.00 observation.
3. Removed historical queue dead letters from the resolved PostgreSQL
   out-of-memory incident without deleting payment, Treasury, or journal facts.
4. Confirmed expired attempts are not inspected after their grace boundary.
5. Traced the final three dead letters to guarded retries for `AUI-WE47`.
   That attempt later settled successfully; durable x-change failure evidence
   remains, while the redundant Laravel queue rows were removed.
6. Expanded the testing canary to five attempts per scheduled run and verified
   the queue drained with no failures.

## Architectural Boundary

The monitor may:

- read provider transactions;
- record immutable payment observations;
- retain provider status history; and
- emit payment-observed events for listeners and broadcast projections.

The monitor must not:

- interpret the business purpose of a payment;
- bypass claim, settlement-envelope, or authorization readiness;
- fabricate or duplicate credits;
- mutate Treasury outside the existing guarded settlement path; or
- use cache as the durable payment ledger.

## Resume Gate

Resume only through the controlled checklist in
[`../todo/payment-monitoring.md`](../todo/payment-monitoring.md). Do not raise
the testing batch, enable another host, or enable production monitoring merely
because the scheduler is healthy.

