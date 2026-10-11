# Settled Payment Feedback Compass

Last updated: 2026-10-11

## North star

Every settled payment has one durable payer-contact association when trustworthy identity exists, and an optional, cost-bounded message to the payer. Payment processing never depends on message delivery.

## Current position

Status: package implementation and focused tests complete in isolated worktrees; host synchronization, package release, cloud testing, and live acceptance remain. SMS is default-off. Funding Settlement, Account Funding Receipt, and Campaign Payment Recognition create durable process records and enqueue the common pipeline. Campaign completion sends one combined message only after the link is ready. The x-feedback daily allowance gates actual provider calls.

## Settled decisions

1. All supported GCash/Maya QR Ph flows participate in payer Contact reconciliation.
2. Checkout bank transfer retains its visitor-supplied Contact fallback but does not qualify for QR payer SMS.
3. The Contact pipe is required; SMS is default-off and enabled separately for top-up, auto-generate, and campaign.
4. Campaign payment and completion use one combined SMS after the link is ready.
5. The existing Campaign completion SMS moves under the same switch and allowance.
6. x-feedback owns an atomic 100 payment-SMS send-attempt cap per Asia/Manila day.
7. A skipped message is never sent later solely because a switch or the daily allowance changed.

## Invariants

- Provider observation alone is not settlement.
- A payer Contact is never assumed to be the issuer or account owner.
- User-entered Contact fields are not replaced by lower-trust provider text.
- SMS failures have no financial or issuance effect.
- One payment produces at most one message for the selected purpose; provider retries are recorded and cost-bounded.
- Queue payloads, logs, and public messages do not expose payer identity beyond the intended SMS recipient.

## Next gates

Review and release x-feedback, then x-change; synchronize the host and x-PayOut apps; testing-instance live QR acceptance; production deployment with SMS disabled; explicit production activation and monitoring.
