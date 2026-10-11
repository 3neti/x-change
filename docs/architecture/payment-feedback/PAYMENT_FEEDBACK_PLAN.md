# Settled Payment Feedback Plan

Last updated: 2026-10-11

## Objective

After a durable payment settlement, run an idempotent, configurable post-settlement pipeline. It links the payer to a Contact and may deliver one payment SMS through x-feedback. Payment, issuance, and redemption must succeed independently of these follow-up effects.

## Boundaries and refactors

- x-change emits a `PaymentSettled` event after commit for Funding Intent settlement, standing Account Funding Receipt settlement, and Campaign QR Ph recognition. The event contains persisted identifiers and purpose, never payer PII.
- A queued listener rehydrates the canonical provider observation and runs configured pipes. Contact reconciliation is the first configured pipe. Feedback is optional and default-off. A durable per-payment processing record and replay command cover lost queue dispatch and retries without texting historical disabled payments.
- Route public Checkout contact work through the shared contact pipe. Preserve Checkout's visitor-supplied bank-transfer contact fallback and late reverified QR identity path. Link every supported GCash/Maya QR payer Contact to its payment process, without treating the account owner as the payer or overwriting person-entered fields.
- x-feedback owns payment SMS templates, delivery evidence, and the payment-SMS allowance. The existing x-change EngageSpark adapter remains the queued provider handoff. x-change owns settlement meaning, payer identity resolution, Contact association, and purpose selection.
- The existing Campaign payment-completion SMS becomes the single combined payment-and-next-steps message. It is queued when the completion link is ready and shares the payment-SMS switch and allowance.

## Cost and message policy

- Default-off master switch plus independent top-up, auto-generate, and campaign switches.
- x-feedback atomically allows at most 100 payment-SMS provider send attempts per Asia/Manila day. A cap-exceeded delivery is journaled as cancelled with `DAILY_CAP_REACHED`, without next-day backfill. Provider retries consume allowance per attempted send.
- Use the gross amount actually paid. Generic text: `You paid ₱{amount}. Thank you.` Campaign copy also includes its completion link and required product disclaimer.
- Resolve generic SMS eligibility from a supported GCash/Maya source account. Skip if no suitable wallet source account exists. Do not send a QR receipt to the issuer, account owner, or a bank-transfer visitor number solely because it was supplied in a form.
- Feedback failure cannot reverse settlement or block issuance, claim, or Contact persistence. Replays retain the decision captured when the payment was first processed.

## Verification and rollout

1. Test the event at each settlement boundary, required contact pipe, bank fallback, late identity reconciliation, third-party top-up, duplicate/replayed events, no historical SMS, concurrent allowance enforcement, and SMS provider failure.
2. Release x-feedback, then x-change. Synchronize the host applications from package releases.
3. Deploy to testing with bounded SMS enabled; make one live QR payment per relevant flow and inspect Contact links, delivery records, provider receipts, and duplicate counts.
4. Deploy x-PayOut production with SMS switches off. Activate selected flows after testing acceptance and record the active configuration and daily-cap observations in the companion compass.
