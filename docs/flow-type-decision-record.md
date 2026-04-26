# Flow Type Decision Record — x-change

## Purpose

This document records the **architectural decisions and rationale** behind the voucher flow model in x-change.

It complements `terminology-lock.md` by explaining **why the system is designed this way**, not just what the terms mean.

---

## 1. Why Pay Code is separate from Voucher

### Decision

We explicitly separate:

- **Pay Code** → external reference
- **Voucher** → internal contract

### Rationale

- allows safe sharing without exposing internal state
- supports multiple presentation formats (QR, SMS, URL)
- enables regeneration / rotation
- aligns with “instruction layer” model from Pay Code philosophy

### Key Insight

> Pay Code is the **handle**, Voucher is the **brain**

---

## 2. The Pay Code Rationale — Push vs Pull

### The Problem We Are Solving

Current digital payment systems (e.g., GCash, BDO Pay, Maya) are primarily:

👉 **Push-based systems**

#### Push Flow Today

1. Sender must know:
    - bank
    - account number
2. Sender initiates transfer
3. Money is pushed to recipient
4. Sender/recipient relies on:
    - screenshots
    - SMS confirmations
    - manual reconciliation

#### Problems with Push

- ❌ requires prior knowledge of recipient account
- ❌ irreversible once sent
- ❌ poor proof structure (screenshots ≠ verifiable records)
- ❌ no structured validation (KYC, identity, presence)
- ❌ fragmented user experience
- ❌ weak audit trail across systems

---

### Pay Code Model

👉 **Pull-based system (like a check, but digital)**

#### Pull Flow (Pay Code)

1. Issuer generates Pay Code
2. Pay Code is shared
3. Recipient (redeemer):
    - presents Pay Code
    - provides required inputs:
        - KYC
        - selfie
        - location
        - signature
4. Recipient chooses destination account
5. System validates and executes disbursement
6. Recipient experiences:
    - guided flow
    - messages / splash screens
    - redirect / completion flow

---

### Why Pull is Better

| Capability | Push (Today) | Pay Code (Pull) |
|----------|-------------|-----------------|
| Requires recipient account upfront | Yes | No |
| Recipient identity validation | None | Strong |
| Proof of transaction | Screenshot | Structured, auditable |
| User experience | Minimal | Rich, guided |
| Flexibility of destination | Fixed | User-chosen |
| Risk of mis-send | High | Low |
| Instruction richness | None | High |

---

### Key Insight

> Pay Code transforms money transfer from  
> **“send to account” → “claim with intent + validation”**

---

### Philosophical Shift

Traditional systems:
```text
Sender decides → money is pushed → recipient adapts
```

x-change (Pay Code model):
```text
Issuer defines intent → recipient fulfills conditions → system executes
```

---

### Real-World Analogy

| Instrument | Behavior |
|----------|--------|
| Bank transfer | push |
| Cash | bearer |
| Check | pull |
| Pay Code | programmable digital check |

---

### Why This Matters

This enables:

- **zero-knowledge transfers** (no account needed upfront)
- **programmable redemption conditions**
- **identity-bound claims**
- **multi-channel UX (QR, SMS, web)**
- **rich settlement flows**
- **verifiable audit trail**

---

### Key Insight

> Pay Code is not just a code  
> It is a **programmable claim instruction**

---

## 3. Why everything goes through the Voucher

### Decision

All flows must be **voucher-mediated**, including:

- disbursement
- payment
- top-up
- settlement

### Rationale

Without this:

- audit trail fragments
- policy enforcement becomes inconsistent
- reconciliation becomes complex
- settlement logic becomes duplicated

### Key Insight

> Voucher is the **single source of truth for financial intent**

---

## 4. Why we have exactly three flow types

### Decision

We define only:

- disbursable
- collectible
- settlement

### Rationale

These represent the **three fundamental financial directions**:

| Type | Direction |
|------|----------|
| disbursable | outbound |
| collectible | inbound |
| settlement | bi-directional |

### Rejected Alternatives

- loan voucher
- insurance voucher
- gift voucher

These are **policy configurations**, not types.

### Key Insight

> Flow type defines **capability envelope**, not use-case

---

## 5. Why delegated spend is modeled as Withdraw (not Collect)

### Decision

Third-party requests are treated as:

👉 **authorized withdrawal**

NOT collection.

### Rationale

- funds leave voucher
- ownership remains clear
- accounting remains correct

### Key Insight

> Direction is determined by **money movement**, not actor**

---

## 6. Why authorization is a separate layer

### Decision

Introduce explicit authorization step:

```text
request → validate → authorize → execute → record
```

### Rationale

- enables delegated spend
- enables OTP and trust policies
- supports risk controls

### Key Insight

> Authorization is a **first-class system layer**

---

## 7. Why Settlement Envelope is not the money engine

### Decision

Envelope is:

- proof container
- readiness gate

NOT:

- balance holder
- executor

### Rationale

- separates compliance from execution
- simplifies financial logic
- enables reuse

### Key Insight

> Envelope governs **when**, Voucher governs **what**

---

## 8. Why collectible vouchers handle self-topup

### Decision

Self-topup must go through collectible vouchers

### Rationale

- preserves audit trail
- avoids dual logic paths
- ensures consistency

### Key Insight

> Even “self” actions must remain **voucher-governed**

---

## 9. Why QR is always voucher-scoped

### Decision

All QR codes resolve to a voucher

### Rationale

- ensures policy enforcement
- ensures auditability
- decouples from payment rail

### Key Insight

> QR is a **presentation of Pay Code**

---

## 10. Why settlement is stage-driven

### Decision

Settlement controlled by:

- settlement_mode
- envelope readiness
- policy rules

### Rationale

Supports:

- loans
- insurance
- escrow

### Key Insight

> Settlement is **time-structured logic**

---

## 11. Design Philosophy Summary

x-change is built on:

1. Voucher-centric orchestration
2. Pay Code as universal entry point
3. Pull-based financial interaction
4. Explicit directionality
5. Strong authorization layer
6. Separation of execution and proof
7. Policy-driven flows

---

## Final Thought

x-change intentionally avoids:

- wallet-centric design
- rail-centric design
- QR-centric design

Instead it defines:

👉 **Voucher-centric, Pay Code-driven financial orchestration**

---

### One-Line Summary

> Pay Code turns money transfer from a **push transaction**  
> into a **programmable, validated, recipient-driven claim experience**

---
