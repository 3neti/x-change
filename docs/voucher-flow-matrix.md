# Voucher Flow Matrix (Phase 1)

## Purpose

This matrix defines the **canonical behavior of vouchers** in x-change after the migration to `3neti/cash`.

It separates:
- **Voucher** → contract, identity, lifecycle
- **Cash** → value container, authorization, execution

This matrix is the **single source of truth** for:
- feature design
- API behavior
- UI flows
- future implementation

---

## Core Dimensions

Each voucher flow is defined by **orthogonal axes**:

- **Flow Type** → disbursable / collectible / settlement
- **Direction** → outward / inward / bilateral
- **Initiator** → who starts the action
- **Authorizer** → who approves (if needed)
- **Recipient** → who receives funds
- **Cash Capability** → what operations are allowed
- **QR Type** → claim / payment / hybrid
- **Envelope Requirement** → proof gating
- **Authorization Policy** → OTP / rules / none
- **Close Condition** → when voucher completes

---

## Primary Flow Matrix

| Flow Type | UX Label | Direction | Initiator | Authorizer | Recipient | Cash Capability | QR Type | Envelope | Close Condition |
|----------|----------|----------|----------|------------|-----------|----------------|--------|----------|----------------|
| `disbursable` | Cash Out Voucher | outward | claimant / requestor | owner or system | claimant / vendor | withdraw + disburse | claim QR | optional | balance = 0 |
| `collectible` | Pay In Voucher | inward | payer | payer / provider | voucher (issuer or owner wallet) | collect | payment QR | optional | target reached or manual close |
| `settlement` | Settlement Voucher | bilateral | staged actors | policy / envelope / owner | staged (borrower, merchant, insurer, etc.) | collect + disburse | hybrid QR | often required | settlement rules satisfied |

---

## Extended Behavioral Matrix

### Disbursable (Cash Out)

| Mode | Description | Initiator | Authorizer | Notes |
|------|------------|----------|------------|------|
| self-withdraw | claimant withdraws funds | owner | system | classic redeem/withdraw |
| delegated spend | third-party requests withdrawal | requestor | owner (OTP) | gift check / POS / peer pay |
| trusted debit | merchant auto-debits small amount | merchant | policy | requires whitelist + limits |

---

### Collectible (Pay In)

| Mode | Description | Initiator | Authorizer | Notes |
|------|------------|----------|------------|------|
| self-topup | owner funds own voucher | owner | provider | always voucher-mediated |
| merchant payment | payer pays merchant | payer | provider | QR scan flow |
| contribution | multiple payers fund voucher | payer(s) | provider | crowdfunding style |

---

### Settlement (Bilateral)

| Mode | Description | Flow |
|------|------------|------|
| disburse_then_collect | loan / credit | funds out → collect back |
| collect_then_release | escrow | funds in → conditional release |
| collect_with_evidence | insurance | proof → collect from insurer |

---

## Authorization Matrix

| Scenario | Authorization Required | Method |
|----------|----------------------|--------|
| self withdraw | no | implicit |
| delegated spend | yes | OTP / approval |
| small amount debit | conditional | policy-based |
| merchant trusted | optional | whitelist |
| settlement release | yes | envelope + rules |

---

## Authorization Policy Axes

Each voucher may define:

```php
'authorization' => [
    'mode' => 'owner_otp',

    'otp' => [
        'required' => true,
        'threshold_amount' => 500,
    ],

    'low_value' => [
        'enabled' => true,
        'max_per_txn' => 100,
        'daily_limit' => 500,
        'no_otp' => true,
    ],

    'trusted_merchants' => [
        'enabled' => true,
        'no_otp' => false,
    ],
];
```

---

## Claim Policy Axis

Defines how balance is consumed:

| Policy | Meaning |
|-------|--------|
| single | full consumption once |
| open | multiple partial withdrawals |
| fixed | predefined slices |

---

## Spend Policy Axis

Defines who can initiate spending:

| Policy | Meaning |
|--------|--------|
| self_only | only owner can withdraw |
| delegated | others can request with approval |
| trusted | whitelisted actors can debit |
| open_request | anyone can request |

---

## Settlement Mode Axis

| Mode | Meaning |
|------|--------|
| none | not a settlement voucher |
| disburse_then_collect | loan model |
| collect_then_release | escrow |
| collect_with_evidence | insurance / proof-driven |

---

## Key Design Rules

### 1. Voucher Type ≠ Behavior

Voucher type defines **capabilities**, not exact behavior.

Actual execution depends on:
- cash policies
- authorization
- settlement rules
- current state

---

### 2. Everything Goes Through the Voucher

All flows must be voucher-mediated:

- disbursement
- payment
- top-up
- settlement
- delegated spend

No direct wallet QR bypass.

---

### 3. Direction Must Be Explicit

Every operation must be one of:

- outward (disburse)
- inward (collect)
- bilateral (settle)

---

### 4. Authorization Is a First-Class Layer

Flow:

```
request → validate → authorize → execute → record
```

Never skip authorization implicitly.

---

### 5. Envelope Is a Gate, Not a Wallet

- governs proofs and readiness
- does not hold funds
- does not execute transfers

---

## Mapping to Real Scenarios

### Gift Voucher (Delegated Spend)

- Type: `disbursable`
- Policy: open
- Spend: delegated
- Authorization: OTP

---

### Self Top-Up

- Type: `collectible`
- Mode: self-topup
- QR: payment QR
- Always voucher-mediated

---

### Merchant POS Payment

- Type: `disbursable`
- Mode: delegated spend
- Authorization: OTP or trusted policy

---

### Loan Voucher

- Type: `settlement`
- Mode: disburse_then_collect

---

### Insurance Claim Voucher

- Type: `settlement`
- Mode: collect_with_evidence
- Envelope: required

---

## Final Summary

A voucher in x-change is:

> A programmable financial contract that governs how cash can move, who can initiate it, who must authorize it, and under what conditions it completes.

This matrix is the foundation for:
- implementation plan
- API design
- UI flows
- policy engine
- testing strategy

---
