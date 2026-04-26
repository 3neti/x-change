# Voucher Flow Matrix (with Pay Code Presentation Flows)

## Purpose

This document extends the Voucher Flow Matrix to explicitly include **Pay Code presentation flows**.

> **Pay Code** = bearer alphanumeric reference (external)  
> **Voucher** = internal contract  
> **Cash** = execution authority

Pay Code is the **entry point of all flows**.  
Every transaction begins with **presentation of a Pay Code**.

---

## Core Principle

> All flows are **presentation-triggered**.

```
present Pay Code → resolve voucher → validate → authorize → execute → record
```

No flow bypasses Pay Code.

---

## Presentation Flow Types

| Flow Category | Description |
|--------------|------------|
| claim presentation | user claims voucher (ownership binding) |
| withdraw request | outward disbursement initiated |
| payment presentation | inward payment initiated |
| settlement presentation | staged bilateral flow |
| delegated request | third-party initiated withdrawal |

---

## Primary Flow Matrix (with Presentation Layer)

| Flow Type | UX Label | Presentation Type | Direction | Initiator | Authorizer | Recipient | Cash Capability | QR Type | Envelope | Close Condition |
|----------|----------|------------------|----------|----------|------------|-----------|----------------|--------|----------|----------------|
| `disbursable` | Cash Out Voucher | claim / withdraw | outward | claimant / requestor | owner/system | claimant / vendor | withdraw + disburse | claim QR | optional | balance = 0 |
| `collectible` | Pay In Voucher | payment | inward | payer | payer/provider | voucher / issuer | collect | payment QR | optional | target reached |
| `settlement` | Settlement Voucher | staged | bilateral | staged actors | policy/envelope | staged recipients | collect + disburse | hybrid QR | often required | settlement rules satisfied |

---

## Pay Code Presentation Flows (Detailed)

### 1. Claim Presentation (Ownership Binding)

| Step | Action |
|-----|-------|
| 1 | user presents Pay Code |
| 2 | system resolves voucher |
| 3 | system collects required inputs |
| 4 | ownership is bound to claimant |
| 5 | voucher becomes usable |

**Notes:**
- zero disbursement possible
- enables delegated spend
- sets owner identity

---

### 2. Withdraw Presentation (Self Claim)

| Step | Action |
|-----|-------|
| 1 | owner presents Pay Code |
| 2 | system validates inputs |
| 3 | system executes withdrawal |
| 4 | funds disbursed |

---

### 3. Delegated Withdraw Request (Request-to-Withdraw)

| Step | Action |
|-----|-------|
| 1 | requestor presents Pay Code |
| 2 | submits amount + destination |
| 3 | system creates claim request |
| 4 | owner receives authorization request |
| 5 | owner approves (OTP / policy) |
| 6 | system executes withdrawal |

**This is the “Pay using Pay Code” flow.**

---

### 4. Payment Presentation (Collectible)

| Step | Action |
|-----|-------|
| 1 | payer scans Pay Code QR |
| 2 | system resolves voucher |
| 3 | payment rail invoked |
| 4 | inbound funds confirmed |
| 5 | voucher balance updated |

---

### 5. Settlement Presentation (Staged)

| Step | Action |
|-----|-------|
| 1 | Pay Code presented |
| 2 | system evaluates stage |
| 3 | checks envelope readiness |
| 4 | allows collect or disburse |
| 5 | updates settlement state |

---

## QR Behavior by Flow

| Flow Type | QR Encodes |
|----------|-----------|
| disbursable | claim endpoint |
| collectible | payment endpoint |
| settlement | dynamic endpoint (stage-aware) |

---

## Authorization Matrix (Presentation-Aware)

| Flow | Requires Authorization | Method |
|-----|----------------------|--------|
| claim binding | optional | OTP / KYC / selfie |
| self withdraw | no | implicit |
| delegated withdraw | yes | owner OTP |
| small debit | conditional | policy |
| merchant debit | optional | whitelist |
| settlement release | yes | envelope + rules |

---

## Authorization Policy (Config)

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

## Claim Policy (Consumption)

| Policy | Behavior |
|-------|--------|
| single | one-time full claim |
| open | multiple partial withdrawals |
| fixed | predefined slices |

---

## Spend Policy (Initiation Control)

| Policy | Behavior |
|--------|--------|
| self_only | only owner can initiate |
| delegated | third-party requests allowed |
| trusted | approved actors can auto-debit |
| open_request | anyone can request |

---

## Settlement Modes

| Mode | Flow |
|------|-----|
| none | not settlement |
| disburse_then_collect | loan |
| collect_then_release | escrow |
| collect_with_evidence | insurance |

---

## Flow Mapping (Real Scenarios)

### Gift Voucher (Delegated Spend)

- Flow: disbursable
- Presentation: delegated withdraw
- Authorization: OTP

---

### Self Top-Up

- Flow: collectible
- Presentation: payment
- Always via Pay Code QR

---

### Merchant POS

- Flow: disbursable
- Presentation: delegated withdraw
- Authorization: OTP or trusted policy

---

### Loan Voucher

- Flow: settlement
- Mode: disburse_then_collect
- Presentation: staged

---

### Insurance Voucher

- Flow: settlement
- Mode: collect_with_evidence
- Envelope required

---

## Key Design Rules

### 1. Pay Code is Always the Entry Point

No direct wallet or rail access.

---

### 2. Presentation Triggers Execution

Pay Code does not hold value.

Execution happens at:
- validation
- authorization
- issuer decision

---

### 3. Direction Must Be Explicit

Every presentation resolves to:
- outward (disburse)
- inward (collect)
- staged (settle)

---

### 4. Authorization is Mandatory When Required

Never implicit for delegated flows.

---

### 5. Envelope is a Gate

- governs readiness
- never executes funds

---

## Final Summary

> Pay Code presentation is the universal trigger for all voucher flows.

It allows:

- claim
- withdraw
- pay
- settle
- delegate

all through a single abstraction:

👉 **present → validate → authorize → execute**

This matrix defines the foundation for:
- API routes
- UI flows
- authorization engine
- payment integration
- settlement orchestration

---
