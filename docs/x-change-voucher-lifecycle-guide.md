# x-change Voucher Lifecycle (Dev / AI Agent Guide)

## Overview

This document describes the end-to-end voucher lifecycle in the `x-change` package, from issuer onboarding to issuance, redemption, withdrawal, and disbursement. It is intended for developers and AI agents working on the codebase.

---

## Phase 1: Onboarding (Issuer + Wallet)

### Goal
Create an entity capable of issuing Pay Codes.

### Flow

```text
Client → /onboarding/issuers → Issuer created
Client → /onboarding/wallets → Wallet provisioned
```

### Key Concepts
- **Issuer** = business identity
- **Wallet** = source of funds
- Can be backed by:
  - `money-issuer`
  - `emi-core` integrations
  - internal wallet abstractions

---

## Phase 2: Issuance (Pay Code Creation)

### Endpoint
`POST /pay-codes`

### Input
- `VoucherInstructionsData`

### Output
- Voucher(s)

### Internal Flow

```text
GeneratePayCodeController
    → GeneratePayCode (Action)
        → VoucherInstructionsData
        → GenerateVouchers::run()
            → Voucher created
            → Cash funded
```

### Key Concepts
- **Voucher** = contract
- **Cash** = value container
- Instructions define:
  - required inputs
  - validation rules
  - settlement rail
  - divisibility / withdrawability

---

## Phase 3: Redemption Preparation (UX Layer)

### Endpoint
`POST /pay-codes/{code}/claim/start`

### Purpose
Translate voucher state and instructions into a UI/form-flow contract.

### Flow

```text
PreparePayCodeRedemptionFlowController
    → PreparePayCodeRedemptionFlow (Action)
        → RedemptionFlowPreparationService
            → Voucher.instructions
            → voucher-redemption.yaml
            → FormFlowInstructionsData
```

### Output
A normalized preparation payload including:
- entry route
- requirements
- flow metadata

### Important Note
This layer does **not** execute money movement. It only defines the UX contract.

---

## Phase 4: Flow Execution (Frontend-driven)

Handled by:
- `3neti/form-flow`
- `voucher-redemption.yaml`

### Dynamic Steps
Depending on the voucher:
- splash
- wallet
- kyc
- bio
- otp
- location
- selfie
- signature

### Output
`collected_data`

Stored by form-flow infrastructure.

---

## Phase 5: Completion Context

### Endpoint
`POST /pay-codes/{code}/claim/complete`

### Purpose
Rehydrate stored form-flow state into normalized execution-ready claim context.

### Flow

```text
LoadPayCodeRedemptionCompletionContext
    → RedemptionCompletionContextService
        → retrieve flow state
        → normalize payload
```

### Output
- normalized flat data
- wallet/bank fields
- claim inputs
- confirmation context

---

## Phase 6: Unified Claim Execution (Canonical Public API)

### Endpoint
`POST /pay-codes/{code}/claim/submit`

This is the canonical public execution endpoint.

### Why it exists
The UI should not need to care whether the voucher is:
- full redeem
- partial withdraw
- open slice
- fixed slice

The package decides internally.

---

## Unified Execution Architecture

```text
SubmitPayCodeClaimController
    → SubmitPayCodeClaimRequest
    → SubmitPayCodeClaim (Action)
        → ClaimExecutionFactory
            → RedeemPayCode OR WithdrawPayCode
                → ExecutionService
                    → Validation
                    → Processing
```

### Pattern Used
- **Factory Method Pattern** for executor selection
- Separate domain execution paths internally
- Unified API contract externally

---

## Internal Path A: Redeem

### When used
- normal / non-withdrawable vouchers
- full claim semantics

### Flow

```text
DefaultRedemptionExecutionService
    → ContextResolver
    → ValidationService
    → ProcessorService
```

### Result
- voucher redeemed
- disbursement flow triggered via downstream abstractions
- feedback / pipeline side-effects may occur

---

## Internal Path B: Withdraw

### When used
- divisible vouchers
- vouchers with slice mode
- withdrawable vouchers

### Flow

```text
DefaultWithdrawalExecutionService
    → DefaultWithdrawalValidationService
    → DefaultWithdrawalProcessorService
        → payout request
        → payout provider / EMI
        → wallet withdrawal
        → voucher metadata update
```

### Important
This is the first layer where real money leaves the system.

---

## Phase 7: Disbursement (External Banking / EMI)

Handled by:
- `emi-core`
- `money-issuer`
- payout provider implementations
- bank registry / settlement utilities

### Responsibilities
- route by settlement rail
- validate rail compatibility
- call external payout providers
- record transaction metadata

---

## Phase 8: Idempotency and Audit Logging

### Idempotency
Applied on money-sensitive endpoints:
- issue
- redeem
- claim submit

### Behavior
- same key + same payload → replay
- same key + changed payload → conflict

### Audit Events
Examples:
- `pay_code.generate.requested`
- `pay_code.generate.succeeded`
- `pay_code.generate.failed`
- `pay_code.redeem.requested`
- `pay_code.redeem.succeeded`
- `pay_code.redeem.failed`
- `pay_code.claim.submit.requested`
- `pay_code.claim.submit.succeeded`
- `pay_code.claim.submit.failed`

---

## Current Lifecycle Summary

### Onboarding
- create issuer
- create issuer wallet

### Issuance
- estimate pay code cost
- generate pay code

### Claim Preparation
- claim/start
- claim/complete

### Claim Execution
- claim/submit
  - internally chooses redeem or withdraw

### Settlement / Disbursement
- payout provider execution
- wallet movement
- metadata persistence

---

## Key Architectural Principles

### 1. UX is decoupled from execution
- YAML driver owns UX flow
- backend owns money and domain semantics

### 2. Public API is unified
- `claim/submit` is canonical
- internal execution remains specialized

### 3. Package owns contracts and defaults
- host app may override services if needed
- package remains self-contained

### 4. Execution paths remain separate internally
- redeem and withdraw are not the same domain action
- they are unified only at the API contract layer

### 5. Money movement is abstracted
- `x-change` orchestrates
- payout / EMI layers execute bank-facing actions

---

## Current Package Capabilities

Implemented:
- issuer onboarding
- issuer wallet opening
- pay code estimation
- pay code generation
- DTO-based response contracts
- redemption preparation
- completion context loading
- redeem execution
- withdraw execution branch
- unified claim submit action
- idempotency
- audit logging

---

## Recommended Future Work

### 1. Reconciliation
- handle pending disbursements
- retry logic
- webhook completion paths

### 2. Observability
- metrics
- traces
- claim execution performance

### 3. Withdrawal Refactoring
- split `DefaultWithdrawalProcessorService` into smaller collaborators if needed

### 4. Compatibility Strategy
- decide whether `/redeem` stays public or becomes compatibility-only
- keep `claim/submit` as the canonical endpoint

---

## Mental Model for Developers / AI Agents

Think of the system as three broad layers:

### A. Contract / UX Layer
- onboarding endpoints
- issuance endpoints
- claim/start
- claim/complete
- YAML form-flow driver

### B. Orchestration Layer
- actions
- DTOs
- execution factory
- claim submit orchestration
- idempotency
- audit logging

### C. Settlement Layer
- payout provider
- EMI integration
- wallet withdrawal
- bank routing
- disbursement metadata

---

## Rule of Thumb

- If you are changing form steps → look at the YAML driver and preparation layer
- If you are changing claim routing → look at the execution factory
- If you are changing redeem vs withdraw semantics → look at their separate execution services
- If you are changing money movement → look at payout / processor services, not controllers

---

## Final Note

`x-change` should be treated as the orchestration package that supersedes host-app-specific redemption flow coupling. The public API should stay stable and claim-first, while the internal redeem/withdraw branches remain explicit, testable, and overridable.
