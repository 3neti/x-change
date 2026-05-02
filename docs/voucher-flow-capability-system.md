# Voucher Flow Capability System

## Purpose

Define a normalized capability model for vouchers to abstract legacy flow types into a consistent execution and UX contract.

This system allows `x-change` to reason about voucher behavior without tightly coupling to legacy terminology.

---

## Core Idea

A voucher is not just a value container.

It is a **capability-driven contract** that defines:

- what can be done (redeem, withdraw, collect)
- what is required (inputs, validation)
- how execution behaves (single-use, divisible, target-based)

---

## Capability Model

### Primary Dimensions

| Capability | Meaning |
|----------|--------|
| `instrument_kind` | cash, payable, collectible |
| `redemption_mode` | redeem, withdraw |
| `flow_type` | disburse, withdraw, collect |
| `is_divisible` | supports partial extraction |
| `target_amount` | required inbound value |
| `cash_amount` | outbound value |

---

## Derived Profiles

### 1. Disbursement Voucher
- `cash.amount > 0`
- `target_amount = 0`

### 2. Withdrawal Voucher
- `cash.amount > 0`
- `is_divisible = true`

### 3. Collectible Voucher
- `cash.amount = 0`
- `target_amount > 0`

---

## Why This Matters

This abstraction allows:

- unified claim API (`claim/submit`)
- dynamic UX generation
- consistent accounting behavior
- elimination of hardcoded flow branching

---

## Collectible Execution Enforcement

Collectible vouchers are now enforced through capability-aware collection services.

Collection execution must pass through:

```php
VoucherCapabilityGuard::ensureCanCollect($voucher)
```

and wallet resolution must use:

```php
VoucherCollectionWalletResolverContract
```

Collection does not rely on `auth()->user()`.

The collection wallet is resolved from persisted voucher metadata, preferably:

```text
instructions.metadata.collection_wallet_id
```

with fallback to:

```text
instructions.metadata.issuer_id
```

This makes collectible execution safe for webhooks and other unauthenticated provider callbacks.

Collectible vouchers may collect funds but may not disburse funds.


## Architectural Alignment

This system aligns with:

- **Voucher Instructions → Capability Extraction**
- **Capability → Flow Preparation**
- **Flow → Execution Path**

As described in:

- voucher lifecycle guide
- redemption architecture note

---

## Guiding Rule

> The voucher defines the capability.  
> The system derives the flow.  
> The user follows the flow
