# x-change Terminology Charter
**Version:** 1.0  
**Status:** Draft (For Adoption)  
**Audience:** Developers, AI Agents, API Designers, Product Integrators

---

## 1. Purpose

This document defines the **canonical terminology** for the x-change ecosystem, covering:

- Internal domain language (code, services, contracts)
- External product language (API, UI, messaging)
- Translation rules between the two

This ensures:
- architectural consistency
- clarity across packages (`x-change`, `voucher`, `cash`)
- stability during major refactors
- alignment between developers and product stakeholders

---

## 2. Core Principle

> **Internal code uses precise domain terms.  
External output uses configurable product terminology.**

---

## 3. Two-Layer Vocabulary Model

### 3.1 Internal Domain Vocabulary

Used in:
- PHP classes
- interfaces
- DTOs
- database schema
- events
- logs
- tests

Must be:
- precise
- unambiguous
- stable
- implementation-oriented

---

### 3.2 External Product Vocabulary

Used in:
- API responses
- UI labels
- notifications
- emails/SMS
- operator dashboards

Must be:
- user-friendly
- configurable
- translatable
- context-aware

---

## 4. Canonical Internal Terminology

### 4.1 Core Entities

| Term | Meaning |
|------|--------|
| `voucher` | Contract defining a Pay Code |
| `voucher_code` | Public identifier |
| `cash` | Value container + execution authority |

---

### 4.2 Lifecycle Actions

| Term | Meaning |
|------|--------|
| `claim` | Public umbrella action to use a voucher |
| `claim_start` | Begin claim process |
| `claim_complete` | Finalize input collection |
| `claim_submit` | Execute claim |

---

### 4.3 Internal Execution Paths

| Term | Meaning |
|------|--------|
| `redeem` | Full voucher consumption |
| `withdraw` | Partial or slice-based extraction |

---

### 4.4 Money Movement

| Term | Meaning |
|------|--------|
| `disburse` | Send money outward |
| `collect` | Receive money inward |
| `settle` | Balance or reconcile funds |

---

### 4.5 Authorization & Identity

| Term | Meaning |
|------|--------|
| `owner` | Primary claimant of cash |
| `requestor` | Actor initiating action |
| `authorization` | Approval step (OTP, mandate, etc.) |
| `mandate` | Delegated permission |

---

## 5. Canonical External Terminology

### 5.1 Product Terms

| Internal | External |
|---------|----------|
| `voucher` | Pay Code |
| `voucher_code` | Pay Code / Code |
| `claim` | Claim |
| `redeem` | Claimed |
| `withdraw` | Withdraw / Cash Out |
| `disburse` | Disburse / Cash Out |
| `collect` | Collect / Receive Payment |
| `settle` | Settlement |

---

### 5.2 Example Message Mapping

Internal:
```
This voucher has already been redeemed.
```

External:
```
This Pay Code has already been claimed.
```

---

## 6. Key Semantic Rules

### 6.1 Claim is the Public Action

> `claim` is the only public-facing execution verb.

Everything else is internal.

---

### 6.2 Redeem vs Withdraw

| Term | Meaning |
|------|--------|
| `redeem` | Full consumption |
| `withdraw` | Partial or repeated extraction |

---

### 6.3 Disburse is Infrastructure

> `disburse` is not a user action.  
It is a system-level payout operation.

---

### 6.4 Do Not Use “Pay” Internally

Avoid `pay` as a domain verb.

Use instead:
- `disburse` (outbound)
- `collect` (inbound)

`pay` is reserved for future UX features like:
- Pay by Code
- Pay by Face

---

## 7. API Naming Policy

### 7.1 Canonical API Routes

```
POST /pay-codes/{code}/claim/start
POST /pay-codes/{code}/claim/complete
POST /pay-codes/{code}/claim/submit
```

---

### 7.2 Legacy Compatibility

Allowed temporarily:
```
/disburse
/redeem
```

Rule:
> Legacy routes are **compatibility-only** and should not define new behavior.

---

## 8. TerminologyService Usage

### 8.1 Responsibilities

The `TerminologyService` must:

1. Resolve product terms
2. Replace placeholders in messages
3. Provide UI-friendly labels

---

### 8.2 Allowed Usage

Use in:
- controllers
- API responses
- UI props
- notifications
- console output (user-facing)

---

### 8.3 Forbidden Usage

Do NOT use in:
- domain logic
- services
- validators
- model methods
- database schema
- events
- internal DTOs

---

### 8.4 Example

```php
$terminology->term('voucher'); // "Pay Code"

$terminology->replace(
    'This :voucher has already been claimed.'
);
```

---

## 9. Naming Rules for Developers

### 9.1 Always Prefer Domain Accuracy

✔ Correct:
- `RedeemPayCode`
- `WithdrawCash`
- `DisburseCash`

✘ Avoid:
- `PayService`
- `DoPayment`
- `HandleMoney`

---

### 9.2 One Concept = One Word

Avoid synonyms:
- do not mix `redeem`, `claim`, `cashout` internally
- pick one and standardize

---

### 9.3 No Overloaded Terms

Avoid ambiguous words:
- `pay`
- `process`
- `handle`

---

## 10. Cross-Package Alignment

### x-change
- orchestration
- API layer
- reporting
- lifecycle coordination

### voucher
- Pay Code identity
- instructions contract
- lifecycle state

### cash
- ownership
- authorization
- validation
- execution
- balance mutation

---

## 11. Migration Guidelines

During refactors:

1. Keep internal terms unchanged
2. Update external terminology via config
3. Avoid renaming DB fields unnecessarily
4. Introduce adapters instead of breaking APIs
5. Maintain backward compatibility for routes

---

## 12. Anti-Patterns

### ❌ Mixing layers
```php
Cash::claimPayCode()
```

### ❌ Using UI terms in domain
```php
PayCodeService::cashOut()
```

### ❌ Using domain terms in UI
```
"Voucher redeemed successfully"
```

---

## 13. Final Guiding Rule

> If it involves money → use precise domain terms  
> If it faces users → use translated product terms

---

## 14. One-Line Summary

**x-change is a claim-first system where vouchers define contracts, cash executes value, and terminology cleanly separates domain precision from product experience.**

---

END OF DOCUMENT
