# NetBank Campaign QR Ph Live Characterization

Date: 2026-09-23

## Scope

Characterize one real incoming payment against a purpose-isolated,
observe-only NetBank Standing Funding Address. This gate does not recognize a
campaign payment, bind coverage, create a settlement envelope, issue a Pay
Code, send a notification, credit Client Funds, or move Treasury.

## Characterization Address

| Property | Result |
|---|---|
| x-change reference | `01M3656EXH0FWZGJKBPGXCNT8J` |
| Purpose | `payment` |
| Recognition mode | `observe_only` |
| Status | `active` |
| QR mode | Static, reusable P2M |
| Embedded amount | No |
| Legacy derivation input | Explicitly authorized, redacted test mobile |

The first attempted mobile was already bound to Account Funding. x-change
rejected cross-purpose reuse before persisting another address.

## Pre-Payment Baseline

The purpose-scoped synchronization returned:

```text
observed=0 settled=0 awaiting_approval=0 suspense=0 applied=0
```

## Redacted Provider Evidence

| Property | Observed value |
|---|---|
| Immutable observations | 1 |
| Observation-key prefix | `0476744c0ff6…` |
| Provider transaction prefix | `4332…` |
| Provider operation identifier | Present |
| Provider request identifier | Absent |
| Gross/net amount | ₱5.37 |
| Provider fee | ₱0.00 |
| Currency | PHP |
| Provider status | `settled` |
| Occurred/settled time | 2026-09-23 03:38:39 UTC |
| Settlement rail | `INSTAPAY` |
| Verification source | `netbank-vca-transaction-history` |
| Normalization | `netbank-standing-credit-v2` |
| Exact destination | Provider-verified |
| Purpose snapshot | `payment` |

No full provider transaction identifier, provider account number, QR payload,
or other sensitive raw value is reproduced in this report.

## First Replay Result

Two consecutive purpose-scoped synchronizations each classified the provider
row as observed. The immutable provider-observation table remained at exactly
one row at that checkpoint. This proved immediate polling replay convergence
for the first settled transaction.

## Second Payment Through the Same QR

The operator paid ₱6.23 through the same static QR. The next synchronization
returned two provider transactions and its immediate replay returned the same
two. Persisted evidence contained:

| Provider transaction | Amount | Evidence versions | Payload versions | Status |
|---|---:|---:|---:|---|
| First | ₱5.37 | 2 | 2 | Settled |
| Second | ₱6.23 | 1 | 1 | Settled |

The durable result is therefore **two distinct provider transactions and three
immutable evidence versions**. The second payment was not conflated with the
first. The additional first-transaction row was caused by a later provider
payload version with a six-second change in `settled_at`, while the transaction
identity, amount, currency, and settled status remained stable. Replaying the
same provider response created no fourth row.

This refines the idempotency model:

- provider transaction identity identifies the economic payment;
- payload hash identifies an immutable evidence version; and
- business recognition must reduce compatible evidence versions to one
  canonical transaction state before applying campaign rules.

## Canonical Reducer Verification

`ReduceProviderFundingTransactionEvidence` now implements that boundary over
the immutable observation ledger. Applied read-only to the live evidence, it
produced exactly two canonical transactions:

| Transaction key | Amount | Status | Canonical settled time | Evidence count |
|---|---:|---|---|---:|
| `1e2b9f00ccd0…` | ₱5.37 | Settled | 2026-09-23 03:38:45 UTC | 2 |
| `3a14540a63ac…` | ₱6.23 | Settled | 2026-09-23 03:45:09 UTC | 1 |

Both canonical projections retained exact-destination verification. Only
one-way transaction-key prefixes are shown.

The reducer permits same-status evidence versions, `pending → processing`,
`pending → settled`, and `processing → settled`. It fails closed on status or
settlement-time regression and on changes to amount, fee, net amount,
currency, occurrence time, destination, provider account, operation/request
identity, settlement rail, or destination-verification evidence.

## Payer Evidence Characterization

A second live read inspected only payer-field presence, source paths, and
lengths. No payer value, provider transaction ID, or raw payload was emitted.

Both payments contained:

| Field | Present | Observed shape |
|---|---|---|
| Payer name | Yes | 14 characters |
| Source account | Yes | 11 characters |
| Source institution code | Yes | 11 characters |
| Explicit payer mobile | No | Absent |
| `source_account` object | Yes | Provider-reported |
| `source_offline_user` object | Yes | No mobile value |
| Legacy `sender` object | No | Absent |

The 11-character account is retained as an account. It is not inferred to be a
mobile number.

The provider-neutral contract now supports optional name, account number,
institution code, and mobile in `ProviderPayerIdentityData`. EMI Core persists
those values in encrypted columns outside observation metadata. NetBank maps
only values present in its VCA transaction history and marks them
provider-reported (`providerVerified=false`). Standing-address reads now use
the existing bounded multi-page NetBank iterator rather than only the first
100 rows.

## Financial Safety Result

| Side effect | Count/result |
|---|---|
| Account Funding receipts | 0 |
| Funding suspense cases | 0 |
| Payment applications | 0 |
| Client Funds credit | None |
| Treasury application | None |
| Coverage/envelope/Pay Code/SMS | Not implemented and not invoked |

## Finding

The provider evidence is sufficient to identify settled, currency- and
destination-qualified incoming credits idempotently. EMI Core now persists the
optional provider-neutral payer identity DTO in encrypted columns, and the
NetBank standing-address normalizer supplies only provider-reported fields.
Those fields are characterized evidence, not proof that the provider verified
the payer's identity.

## Remaining Gate 1 Evidence

Before starting business recognition:

1. characterize reversals or returns through documented provider semantics;
2. establish the bounded polling/window policy above the already bounded
   provider pagination; and
3. move new hosts to `netbank-account-hmac-v2` rather than relying on a mobile
   as the address namespace.
