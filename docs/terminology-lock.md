# Terminology Lock — x-change

## Purpose

This document defines the **official terminology** of the x-change system.

Its purpose is to:

- eliminate ambiguity across engineering, product, and documentation
- ensure consistent naming across packages (`x-change`, `cash`, `instruction`, etc.)
- align internal architecture with external (user-facing) language
- serve as the **single source of truth** for naming decisions

> ⚠️ This is a **LOCKED document**.  
Changes must be deliberate and versioned.

---

## Core Concept Hierarchy

```text
Pay Code (external reference)
    ↓ resolves to
Voucher (contract / policy)
    ↓ governs
Cash (execution authority)
    ↓ triggers
Flow (operation)
```

---

## 1. Pay Code

### Definition

A **Pay Code** is a **bearer reference string** used to access a voucher.

### Characteristics

- externally visible
- shareable (QR, SMS, URL)
- does NOT hold value
- does NOT execute logic
- resolves to a voucher

### Examples

- `ABCD-1234`
- QR code
- `/pay?code=XXXX`

### Rules

- Pay Code is **always required** to initiate a flow
- Pay Code is **not the voucher**
- Pay Code is **not a wallet**
- Pay Code is **not an account**

---

## 2. Voucher

### Definition

A **Voucher** is a **policy-governed financial contract**.

### Responsibilities

- defines rules
- holds state
- tracks balance semantics
- governs flows
- links to settlement envelope (optional)

### Key Properties

- flow_type (disbursable, collectible, settlement)
- claim_policy (single, open, fixed)
- spend_policy (self, delegated, trusted)
- authorization_policy
- settlement_mode
- owner (optional)
- issuer

### Important

> Voucher is the **primary governed object in the system**

---

## 3. Cash

### Definition

A **Cash object** is the **execution authority** tied 1:1 to a voucher.

### Responsibilities

- validates secrets / identity
- enforces authorization rules
- executes money movement
- interacts with payment rails

### Important

> Voucher defines WHAT is allowed  
> Cash enforces HOW it is executed

---

## 4. Flow Types

Flow types define **capability envelopes**, not behavior alone.

### 4.1 Disbursable

**Code:** `disbursable`  
**UI:** Cash Out Voucher

#### Definition

Voucher that allows **outbound disbursement of funds**

#### Capabilities

- withdraw
- delegated spend (optional)
- slicing (optional)

#### Examples

- gift check
- ayuda payout
- reimbursement

---

### 4.2 Collectible

**Code:** `collectible`  
**UI:** Pay In Voucher

#### Definition

Voucher that allows **inbound collection of funds**

#### Capabilities

- payment QR
- target-based funding
- self-topup

#### Examples

- wallet top-up
- merchant payment
- bill payment

---

### 4.3 Settlement

**Code:** `settlement`  
**UI:** Settlement Voucher

#### Definition

Voucher that supports **bi-directional, policy-driven flows**

#### Capabilities

- collect funds
- disburse funds
- staged execution
- envelope gating

#### Examples

- loans
- insurance claims
- escrow
- contract settlement

---

## 5. Directional Operations

Every operation must be explicitly classified.

| Operation | Direction | Description |
|----------|----------|-------------|
| claim | none | ownership binding |
| withdraw | outward | disbursement |
| collect | inward | payment into voucher |
| settle | bilateral | staged flow |

---

## 6. Presentation Flows

All flows are initiated via Pay Code.

### 6.1 Claim (Ownership Binding)

- binds owner to voucher
- enables authorization channel

---

### 6.2 Withdraw (Self)

- owner disburses funds

---

### 6.3 Request Withdraw (Delegated)

- third-party requests withdrawal
- requires authorization

> ⚠️ This is NOT “collection”  
It is **authorized withdrawal**

---

### 6.4 Payment (Collectible)

- payer sends funds into voucher

---

### 6.5 Settlement (Staged)

- flow determined by policy + envelope

---

## 7. Authorization

### Definition

Authorization is the **explicit approval step** before execution.

### Methods

- OTP (default)
- trusted merchant
- low-value auto approval
- future: device / biometric

### Rule

> Delegated flows MUST be authorized

---

## 8. Policies

### 8.1 Claim Policy

| Policy | Description |
|-------|------------|
| single | one-time use |
| open | multiple partial claims |
| fixed | predefined slices |

---

### 8.2 Spend Policy

| Policy | Description |
|--------|------------|
| self_only | owner only |
| delegated | third-party request allowed |
| trusted | approved actors auto-debit |
| open_request | public request allowed |

---

### 8.3 Settlement Mode

| Mode | Description |
|------|------------|
| none | not settlement |
| disburse_then_collect | loan |
| collect_then_release | escrow |
| collect_with_evidence | insurance |

---

## 9. Settlement Envelope

### Definition

A **Settlement Envelope** is a **non-financial artifact container**.

### Responsibilities

- stores proofs (KYC, selfie, location, signature)
- governs readiness
- enables compliance and validation

### Important

> Envelope is a **gate**, not an execution engine

---

## 10. QR Types

| Type | Purpose |
|------|--------|
| claim QR | ownership binding |
| payment QR | inbound collection |
| hybrid QR | settlement |

---

## 11. Key Distinctions (Critical)

### Pay Code vs Voucher

| Pay Code | Voucher |
|----------|--------|
| external | internal |
| reference | contract |
| stateless | stateful |
| trigger | governs |

---

### Withdraw vs Collect

| Withdraw | Collect |
|----------|--------|
| money goes out | money comes in |
| disbursement | payment |
| owner-controlled | payer-controlled |

---

### Delegated Withdraw vs Collect

| Delegated Withdraw | Collect |
|-------------------|--------|
| requires owner approval | requires payer action |
| outward | inward |
| uses OTP | uses payment rail |

---

## 12. Naming Conventions

### Code (strict)

- disbursable
- collectible
- settlement

### UI (friendly)

- Cash Out Voucher
- Pay In Voucher
- Settlement Voucher

---

## 13. Design Principles

1. **Everything goes through the voucher**
2. **Pay Code is the universal entry point**
3. **Cash enforces execution**
4. **Direction must always be explicit**
5. **Authorization is mandatory when required**
6. **Envelope governs readiness, not money**
7. **Flows are presentation-driven**

---

## 14. Final Summary

x-change defines a unified model where:

- **Pay Code** → entry point
- **Voucher** → contract
- **Cash** → execution
- **Flow** → behavior

All financial interactions are:

```text
present → validate → authorize → execute → record
```

This terminology is now **locked** and must be followed across:

- codebase
- APIs
- documentation
- UI/UX
- integrations

--
