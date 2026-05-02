# Collectible Voucher Execution

## Purpose

A collectible voucher represents a request to receive money.

Unlike a disbursable voucher, a collectible voucher does not contain prepaid cash value at issuance. It points a payer to a payment route, receives payment confirmation, credits the issuer wallet, records the collection, and tracks progress toward the target amount.

---

## Accounting Model

Collectible vouchers use the following accounting structure:

```php
[
    'cash' => [
        'amount' => 0,
        'currency' => 'PHP',
    ],

    'target_amount' => 100.00,

    'metadata' => [
        'flow_type' => 'collectible',
        'issuer_id' => '...',
        'collection_wallet_id' => '...',
    ],
]
```

## Core Rule

```text
cash.amount = prepaid value
target_amount = amount requested
```

For collectible vouchers:

```text
cash.amount must be 0
target_amount must be greater than 0
```

The target amount is not debited from the issuer wallet at issuance.

---

## Execution Flow

```text
issue collectible voucher
→ generate payment QR
→ payer pays externally
→ payment confirmation received
→ issuer wallet credited
→ collection ledger row recorded
→ collection progress updated
```

---

## Wallet Credit Timing

The issuer wallet is credited only after successful payment confirmation.

Successful confirmation may come from:

```text
POST /vouchers/code/{code}/payment-confirmations
POST /payment/webhooks/{provider}
```

Failed payment confirmations are recorded but do not credit the wallet.

---

## Wallet Resolution

Collection no longer depends on `auth()->user()`.

The collection wallet is resolved from voucher metadata:

```text
instructions.metadata.collection_wallet_id
instructions.metadata.issuer_id
```

Preferred field:

```text
collection_wallet_id
```

Fallback field:

```text
issuer_id
```

This makes collection safe for:

- unauthenticated payment callbacks
- provider webhooks
- console lifecycle scenarios
- QR-driven payment flows

---

## Payment Confirmation

Manual/API confirmation accepts a provider-normalized payload:

```json
{
  "amount": 100.00,
  "currency": "PHP",
  "status": "succeeded",
  "provider": "manual",
  "provider_reference": "REF-123",
  "provider_transaction_id": "TXN-123",
  "idempotency_key": "idem-123",
  "payer": {
    "name": "Juan Dela Cruz",
    "mobile": "09171234567"
  }
}
```

A successful confirmation returns a collected payment result and credits the issuer wallet.

---

## Idempotency Rules

Collection must not double-credit a wallet.

Duplicate protection applies to:

```text
idempotency_key
provider + provider_reference
```

Expected behavior:

| Replay Type | Result |
|---|---|
| Same idempotency key + same payload | Previous result is replayed |
| Same idempotency key + different payload | Conflict |
| Same provider reference + same payload | Previous result is replayed |
| Same provider reference + different payload | Conflict |

---

## Provider Webhook Flow

Webhook ingestion follows this flow:

```text
receive webhook
→ resolve parser by provider
→ parse voucher code and payment result
→ resolve voucher
→ resolve collection wallet from voucher metadata
→ collect funds
→ record ledger
→ return provider-safe response
```

Webhook route:

```text
POST /payment/webhooks/{provider}
```

Unsupported providers fail safely.

Duplicate provider events are idempotent and must not double-credit the wallet.

---

## Collection Ledger

Each collection attempt is recorded in `voucher_collections`.

Important fields:

```text
voucher_id
collection_number
status
requested_amount_minor
collected_amount_minor
currency
provider
provider_reference
provider_transaction_id
payer_mobile
payer_name
wallet_transaction_id
idempotency_key
attempted_at
completed_at
failure_message
meta
```

Successful and failed attempts are both recorded.

Only successful collections count toward collection progress.

---

## Collection Progress

Collection progress is computed from successful collection rows.

```text
collected_total = sum(successful collected_amount)
remaining_to_collect = target_amount - collected_total
is_fully_collected = collected_total >= target_amount
```

Progress metadata is persisted on the voucher:

```json
{
  "collection_progress": {
    "target_amount_minor": 10000,
    "collected_total_minor": 10000,
    "remaining_to_collect_minor": 0,
    "is_fully_collected": true,
    "is_overpaid": false,
    "overpaid_amount_minor": 0
  }
}
```

---

## Overpayment

If collected amount exceeds target amount:

```text
is_overpaid = true
overpaid_amount = collected_total - target_amount
```

Overpayment handling is tracked but policy decisions such as refunding, accepting, or rejecting overpayment may be handled by future provider/policy rules.

---

## Optional Auto-Close

Collectible vouchers may optionally auto-close when the target amount is fully collected.

Initial behavior:

```text
collection progress records full collection
voucher metadata may record auto-close state
```

Full closure policy can be expanded later.

---

## Capability Rules

Collectible vouchers:

Allowed:

```text
payment QR generation
payment confirmation
payment webhook collection
wallet credit
collection ledger
collection progress tracking
```

Denied:

```text
redeem-as-cash-out
withdrawal claims
disbursement
outward settlement
```

---

## Boundary

This implementation does not yet provide:

- production payment provider credentials
- full Netbank QR restoration
- settlement envelope submission
- escrow release
- loan repayment lifecycle
- delegated spend authorization
