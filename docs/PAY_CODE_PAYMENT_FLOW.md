# Pay Code Payment Flow

**Version:** 1.0  
**Last Updated:** 2026-07-24

This document describes the public payment experience for collectible Pay
Codes. It is deliberately separate from Account funding.

## Money Direction

| Experience | Money direction | Durable record |
|---|---|---|
| `/x/claim/{code}` | x-change sends value to a beneficiary | Voucher Claim |
| `/x/pay/{code}` | a payer sends value to a collectible Pay Code | Payment Attempt → Voucher Collection |
| `/x/cockpit/funding` | an authenticated owner funds their own Account | Funding Intent or Account Funding Receipt |

A collectible payment never creates a Funding Intent, Account Funding Receipt,
Treasury funding recognition, or Account top-up. The reusable Account Funding
QR remains long-lived and unchanged.

## Public Flow

```text
GET /x/pay/{code}
  → read collectible capability and unpaid amount
  → no provider call and no money movement

POST /x/pay/{code}/attempts
  → create/replay one session-bound Payment Attempt
  → calculate the exact remaining collectible amount
  → generate a fresh NetBank pre-transaction token
  → register one expiring VCA and exact dynamic P2M QR Ph
  → encrypt provider reference, destination, and QR instructions

POST /x/pay/{code}/attempts/{attempt}/checks
  → query authoritative NetBank VCA history
  → settle, continue awaiting, or enter suspense
```

The QR is returned only on a `no-store` page to the browser session that
created the Payment Attempt. Knowing the attempt reference is insufficient to
open it from another browser session.

The Vue component is published as `x-change/claim/Payment`. That component
name deliberately uses the package's existing public-claim namespace so the
host's established Inertia layout resolver renders `/x/pay/{code}` without
authenticated dashboard chrome. The URL and payment domain remain separate
from `/x/claim`; this is an integration namespace, not a money-flow alias.

The package asset doctor guards both the public claim/payment components and
Cockpit components. A missing host publication therefore fails
`x-change:doctor --assets` instead of becoming a blank Inertia page.

## Instruction Failure and Retry

Provider instruction creation is not payment evidence. If NetBank rejects or
cannot complete token, VCA, or QR creation:

- the Payment Attempt stays `pending_instructions`;
- a sanitized `provider_instruction_failed` event is appended;
- the public response contains no provider body, credentials, account number,
  token, or exception details;
- the payer sees that no payment was recorded and may retry; and
- the browser session reuses the same idempotent Payment Attempt.

Only a successfully validated QR moves the attempt to `awaiting_payment`.

## Authoritative Verification

The browser never submits an amount, provider transaction, payment status, or
destination to the verification endpoint. **Check NetBank** supplies only the
Payment Attempt identity. x-change reloads all expected facts from encrypted
server-side state and queries the provider adapter.

Settlement requires all of the following:

- provider status is `settled`;
- gross and net amounts both equal the exact Payment Attempt amount;
- currency matches;
- provider settlement time is present;
- the snapshotted destination is verified; and
- the provider transaction has not been applied before.

A pending/processing observation returns the attempt to `awaiting_payment`.
Missing evidence also remains awaiting. Provider outages record only a
sanitized exception type. Amount, currency, destination, fee, or terminal
status mismatches enter `suspense` and do not mutate a wallet.

An exact match runs one database transaction that locks both the Payment
Attempt and voucher, deposits into the voucher collection wallet, writes one
Voucher Collection, persists collection progress, and marks the attempt
settled. Rechecking a settled attempt returns the existing result.

## Hybrid Checks and Expiry

Payers may use **Check NetBank** for an immediate history query. The package
also registers this scheduler entry every minute:

```bash
php artisan xchange:payments:verify-open --provider=netbank --limit=100
```

The command dispatches unique, non-overlapping, provider-rate-limited jobs.
Scheduled and payer checks use the same verification action. Checks continue
through the configured five-minute settlement grace period, after which an
unpaid attempt becomes `expired`.

NetBank webhooks remain intake hints rather than settlement authority. A
payment can be discovered without a webhook because both manual and scheduled
paths query VCA history. Direct webhook-to-Payment-Attempt wake-up may be added
later without changing settlement authority.

## NetBank Token Rule

NetBank support confirmed on 2026-07-24 that generating a new
pre-transaction-validation token does not overwrite or invalidate an existing
token; each generated token is intended for a new VCA registration.

Accordingly:

- the long-lived Account Funding QR does not consume a new token;
- each new Payment Attempt generates a fresh token for a fresh expiring VCA;
- retrying the same Payment Attempt reopens its stored instructions and does
  not register another VCA.

## Configuration

```dotenv
X_CHANGE_PAYMENT_ATTEMPTS_ENABLED=true
X_CHANGE_PAYMENT_ATTEMPT_PROVIDER=netbank
X_CHANGE_PAYMENT_ATTEMPT_EXPIRES_AFTER_MINUTES=15
# Use a stable, secret value in production; APP_KEY is the fallback.
# X_CHANGE_PAYMENT_ATTEMPT_HASH_KEY=base64:...
```

Package defaults additionally configure:

- public read/start/check throttles;
- a 30-second instruction lock;
- a 120-second verification lock;
- 30 provider queries per minute;
- a 100-attempt scheduled batch; and
- a 300-second settlement grace period.

NetBank payment availability still depends on the existing EMI NetBank
credentials, corporate account, five-digit VCA alias, QR endpoint, and merchant
QR fields. These remain runtime configuration; no credentials belong in this
document or in logs.

## Current Limits

- exact-amount dynamic P2M QR only;
- one provider (`netbank`) selected by configuration;
- payer identity may be retained as provider evidence but never routes money;
- fees are not silently deducted from a Pay Code payment—non-zero net/gross
  differences enter suspense;
- suspense resolution remains an operator-controlled future extension.

## Acceptance Record — 2026-07-24

Browser acceptance used fresh package lifecycle fixtures and did not submit a
real payment or claim:

- `/x/claim/TEST-BV65` rendered the claim entry at 1280×900 and 390×844 with
  no horizontal overflow; configured Rider disclosures remained dismissible;
- `/x/claim/PAY-A3F2` correctly rejected a collectible Pay Code as an outward
  claim;
- `/x/pay/PAY-A3F2` rendered as a standalone public page at both widths;
- the NetBank sandbox rejected live token generation with HTTP 400;
- the corrected public boundary converted that rejection into the sanitized
  retry notice, kept the attempt pending, and recorded no payment; and
- no new browser console error was produced after the corrected retry.

Live settlement UAT remains separate: a human must scan and pay a deliberately
small exact amount after NetBank confirms the production token/VCA setup.
