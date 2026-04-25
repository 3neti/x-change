# Withdrawal Pipeline Documentation

## Overview

The withdrawal flow in **x-change** is now fully orchestrated by a modular `WithdrawalPipeline`.

This replaces the previous monolithic logic in `DefaultWithdrawalProcessorService` and introduces a **step-based architecture** that is:

- Extensible
- Testable
- Bank/EMI configurable
- Future-proof for compliance and fraud controls

---

## High-Level Flow

```
DefaultWithdrawalProcessorService
            ↓
   WithdrawalPipeline
            ↓
   [Ordered Pipeline Steps]
            ↓
   Final Withdrawal Result
```

The processor is now a **thin adapter** that:
- Accepts input
- Calls the pipeline
- Returns the final result

---

## Pipeline Context

All steps operate on a shared:

```
WithdrawalPipelineContextData
```

This object carries state across steps, including:

- voucher
- contact (claimant)
- amount
- bankAccount
- payoutRequest
- execution (disbursement result)
- settlement
- finalResult

Think of this as the **single source of truth** during execution.

---

## Current Pipeline Steps

### 1. ResolveWithdrawalClaimantStep
Resolves the claimant (Contact) from input.

**Responsibility:**
- Normalize and resolve mobile → Contact
- Attach to context

> ⚠️ TODO: Standardize mobile normalization (E.164 vs local format)

---

### 2. AssertWithdrawalEligibilityStep
Validates whether the voucher can be withdrawn.

**Delegates to:** `3neti/cash`

Checks include:
- Withdrawable flag
- Expiry
- Active state
- Slice availability

---

### 3. AuthorizeWithdrawalClaimantStep
Ensures claimant is allowed to withdraw.

Examples:
- Mobile lock
- Ownership checks

---

### 4. ResolveWithdrawalAmountStep
Determines withdrawal amount.

Supports:
- Explicit amount (slice withdrawal)
- Full redemption

---

### 5. ResolveWithdrawalBankAccountStep
Extracts and validates bank account details.

---

### 6. BuildWithdrawalPayoutRequestStep
Builds `PayoutRequestData` used for disbursement.

---

### 7. GuardWithdrawalRailStep
Ensures the selected settlement rail is allowed.

---

### 8. ExecuteWithdrawalDisbursementStep
Executes payout via disbursement executor.

**Behavior:**
- Calls provider (e.g., NetBank)
- On failure:
    - Records pending disbursement
    - Rethrows exception

---

### 9. WithdrawalWalletSettlementStep
Performs wallet-level settlement.

**Responsibilities:**
- Deduct funds from voucher wallet
- Record ledger transactions

> 🔴 Critical: This step touches actual money movement.

---

### 10. BuildWithdrawalResultStep
Constructs final response via `WithdrawalResultFactory`.

Returns:
- Status
- Message
- Claim details
- Ledger entries

---

## Key Design Principles

### 1. Single Responsibility per Step
Each step does one thing and does it well.

---

### 2. Context-Driven Flow
No step returns data — all mutate the shared context.

---

### 3. Fail Fast
Validation steps throw immediately:
- Prevents partial execution
- Keeps flow predictable

---

### 4. Side Effects Are Isolated
Only specific steps:
- Execute disbursement
- Perform settlement

Everything else is pure orchestration.

---

### 5. Externalized Domain Logic
- Eligibility → `3neti/cash`
- Disbursement → EMI provider
- Contact resolution → contact package

---

## Extension Points (Future)

The pipeline is now ready to support:

### Compliance & Security
- OTP validation step
- KYC verification step
- Selfie/liveness step
- Location validation step

### Risk & Fraud
- Fraud scoring
- Velocity limits
- Behavioral checks

### Business Rules
- Vendor authorization
- Merchant-specific rules
- Tiered withdrawal limits

### Infrastructure
- Rate limiting
- Circuit breakers
- Retry strategies

---

## Example: Adding OTP Step

Insert after claimant resolution:

```php
OtpValidationStep::class,
```

No other changes required.

---

## Testing Strategy

Each step is:
- Unit tested independently
- Verified through pipeline tests
- Covered by lifecycle (end-to-end) tests

---

## Current Status

- ✅ Pipeline fully operational
- ✅ Processor reduced to adapter
- ✅ Settlement and disbursement integrated
- ✅ Full test suite passing

---

## What This Enables

This architecture allows:

- Banks to plug their own rules
- EMIs to control disbursement behavior
- Rapid experimentation without breaking core flow

---

## Summary

We have successfully transformed:

```
Old:
Monolithic Withdrawal Processor

New:
Composable, Step-Based Pipeline
```

This is a foundational shift — not just a refactor.

This is now **production-grade orchestration architecture**.
