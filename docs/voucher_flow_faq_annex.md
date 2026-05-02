# Voucher Flow FAQ — Annex (Implementation & Runtime)

## Purpose

This annex contains **implementation-level FAQs** that support developers and integrators.

It complements:
- `voucher-flow-matrix.md` → conceptual model
- `flow-type-decision-record.md` → architectural reasoning
- `voucher_flow_faq.md` → business + conceptual FAQ

This file focuses on:

👉 runtime behavior  
👉 debugging  
👉 persistence rules  
👉 integration pitfalls

---

## 1. Where is `flow_type` stored?

`flow_type` is stored inside the voucher’s instruction metadata:

```
voucher.metadata.instructions.metadata.flow_type
```

### Example

```php
'instructions' => [
    'metadata' => [
        'flow_type' => 'collectible',
    ],
]
```

---

## 2. How does the system resolve flow type?

Resolution order:

1. `instructions.metadata.flow_type`
2. fallback (legacy or default behavior)

### Resolver

```php
VoucherFlowCapabilityResolver::resolve($voucher)
```

---

## 3. Why is my voucher behaving as disbursable?

Because:

- `flow_type` is missing
- OR incorrectly injected

### Default behavior

```
flow_type = disbursable (fallback)
```

### Fix

Ensure:

```php
data_set($instructions, 'metadata.flow_type', 'collectible');
```

---

## 4. Where should `flow_type` be injected?

NOT in:
- controllers ❌
- runtime resolver ❌

ONLY at issuance time:

```
Scenario → Instructions → Voucher
```

### Correct place

- lifecycle command
- issuance service
- instruction builder

---

## 5. Why is `flow_type` not persisting?

Most common causes:

### ❌ Injected at wrong level

Wrong:

```php
$scenario['metadata']['flow_type']
```

Correct:

```php
$instructions['metadata']['flow_type']
```

---

### ❌ Overwritten during normalization

Check:
- instruction normalizers
- metadata builders

---

### ❌ Not passed to voucher package

Verify:

```
GeneratePayCode
→ GenerateVouchers
→ VoucherInstructionsData
```

---

## 6. How do I verify `flow_type` is persisted?

### Debug

```php
dd(data_get($voucher->metadata, 'instructions.metadata.flow_type'));
```

Expected:

```
"collectible"
```

---

## 7. Why is my collectible voucher generating claims?

Because:

```
claims[] is not empty in scenario config
```

### Fix

```php
'claims' => []
```

---

## 8. Why does the lifecycle command hang?

Because:

```
--accept-pending is missing
```

### Required flags

```bash
--timeout=1
--poll=1
--accept-pending
```

---

## 9. Why do I get "target amount required"?

Because:

```
voucher_type = payable
```

### Rule

| voucher_type | requirement |
|-------------|------------|
| payable | requires target_amount |
| non-payable | no target needed |

### Fix

Remove:

```php
'voucher_type' => 'payable'
```

---

## 10. Can `flow_type` be changed after issuance?

No.

### Reason

- it defines capability
- affects ledger behavior
- affects QR routes

### Rule

> Flow type is immutable.

---

## 11. How does flow_type affect QR?

| flow_type | QR behavior |
|----------|-----------|
| disbursable | claim / withdraw |
| collectible | payment |
| settlement | dynamic |

---

## 12. How does flow_type affect capabilities?

| flow_type | can_collect | can_disburse |
|----------|------------|-------------|
| disbursable | false | true |
| collectible | true | false |
| settlement | true | true |

---

## 13. Why is this inside `instructions.metadata`?

Because:

- instructions define execution
- metadata travels with instructions
- voucher is just a container

### Key idea

> Voucher = container  
> Instructions = behavior  
> Metadata = configuration

---

## 14. What is the full flow pipeline?

```
Scenario
→ Instructions
→ Inject flow_type
→ GeneratePayCode
→ GenerateVouchers
→ Persist metadata
→ Resolve capabilities
→ Execute flows
```

---

## 15. What is the most common mistake?

> Treating flow_type as a voucher property instead of an instruction property

---

## Why is `cash.amount` zero for collectible vouchers?

Because collectible vouchers are not prepaid.

They represent a request to receive funds. The requested amount belongs in `target_amount`, not `cash.amount`.

---

## When is the issuer wallet credited?

Only after a successful payment confirmation or provider webhook.

Issuance does not credit or debit the target amount.

---

## Why does collection not use `auth()->user()`?

Payment providers and webhook callbacks usually do not operate inside an authenticated browser session.

The collection wallet must be resolved from voucher metadata so the flow works safely for APIs, webhooks, QR flows, and console scenarios.

---

## What prevents duplicate payment confirmation?

The collection layer applies idempotency using:

```text
idempotency_key
provider + provider_reference
```


Duplicate confirmations replay the previous result when the payload matches. Conflicting replays are rejected.

---

## What happens if payment fails?

A failed collection attempt is recorded in the collection ledger, but the issuer wallet is not credited and collection progress is not updated.

---

## Can a collectible voucher be withdrawn?

No.

Collectible vouchers may collect inward payment. They cannot be redeemed or withdrawn as outward cash value.

## Final Insight

> Flow type is not a label.  
> It is the **execution contract of the voucher**.

---
