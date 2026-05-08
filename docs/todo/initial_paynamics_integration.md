# AI Agent Strategy and Implementation Plan
# Paynamics Production Onboarding via Lifecycle Scenario Runtime

## Purpose

This document defines the recommended operational and architectural strategy for onboarding a new payout provider into the x-change ecosystem using the Lifecycle Scenario Runtime.

This document is specifically intended for:

- AI coding agents
- integration agents
- operational automation agents
- provider onboarding agents
- lifecycle scenario generation agents

The goal is to:

```text
minimize blind trial-and-error integration work
```

while maximizing:

- deterministic onboarding
- operational validation
- replayability
- provider parity
- lifecycle compatibility

---

# Important Context

## Existing Operational State

The following components are already operational and should be treated as:

```text
KNOWN WORKING BASELINES
```

### Working Components

| Component | Status |
|---|---|
| x-change lifecycle runtime | working |
| lifecycle scenario engine | working |
| divisible/open slice scenarios | working |
| emi-core | working |
| emi-netbank | working |
| Netbank payout orchestration | working |
| provider polling/reconciliation | working |
| runtime orchestration | working |

The Netbank integration is the:

```text
canonical operational reference implementation
```

for provider onboarding.

---

# Important Operational Assumption

## Paynamics Was Previously Working

The Paynamics integration:

```text
emi-paynamics
```

was previously operational against:

```text
sandbox/staging infrastructure
```

This means:

```text
the architectural integration model is already valid
```

The onboarding effort is therefore NOT:

```text
greenfield integration work
```

It is instead:

```text
production operationalization and provider certification
```

---

# Critical AI Agent Rule

The AI agent MUST NOT:

- redesign the architecture
- invent new abstractions
- rewrite emi-core
- replace lifecycle orchestration
- redesign payout contracts
- redesign runtime orchestration

unless explicitly instructed.

The AI agent should instead assume:

```text
the current architecture is fundamentally correct
```

and should focus on:

```text
operational compatibility and provider parity
```

---

# Primary Goal

The primary onboarding objective is:

```text
Make Paynamics operationally equivalent to Netbank
```

for the purposes of:

- payout orchestration
- lifecycle scenarios
- provider reconciliation
- runtime orchestration
- settlement validation

---

# IMPORTANT LIMITATION

The onboarding scope is currently limited to:

```text
centralized settlement wallet orchestration
```

NOT:

- per-user wallet orchestration
- external wallet authorities
- wallet federation
- delegated balances
- hybrid custody models

These may be implemented later.

For now, Paynamics should behave operationally like:

```text
centralized payout provider
```

similar to Netbank.

---

# Existing Operational Model

Current x-change operational model:

```text
x-change
    ↓
emi-core PayoutProvider
    ↓
Provider Adapter
    ↓
Centralized Settlement Account / Wallet
    ↓
External Bank Disbursement
```

Netbank already implements this successfully.

Paynamics should initially conform to the same model.

---

# AI Agent Operational Methodology

The AI agent should follow the phases below STRICTLY and sequentially.

The AI agent should NOT skip phases.

The AI agent should NOT attempt full lifecycle execution until earlier phases succeed.

---

# Phase 1 — Environment Readiness

## Objective

Validate credentials and runtime configuration.

## AI Agent Responsibilities

Validate:

```env
CONSTELLATION_BASE_URL
CONSTELLATION_USERNAME
CONSTELLATION_PASSWORD
CONSTELLATION_MERCHANT_KEY
CONSTELLATION_SETTLEMENT_WALLET_ID
CONSTELLATION_REVENUE_WALLET_ID
```

Validate:

- Laravel config loading
- environment hydration
- HTTP client readiness
- SSL/TLS connectivity

## Success Criteria

The AI agent must confirm:

```text
credentials load successfully
```

before proceeding.

---

# Phase 2 — Connectivity Validation

## Objective

Validate transport and authentication.

## AI Agent Responsibilities

Run only:

```bash
php artisan constellation:probe
```

The AI agent should NOT execute payouts yet.

## Success Criteria

The AI agent must confirm:

- HTTP connectivity
- authentication success
- merchant identity success
- provider availability

before continuing.

---

# Phase 3 — Infrastructure Wallet Validation

## Objective

Validate operational settlement wallet readiness.

## AI Agent Responsibilities

Run:

```bash
php artisan constellation:setup --verify
```

Then:

```bash
php artisan constellation:wallet-details {walletId}
```

Then:

```bash
php artisan constellation:wallet-balance {walletId}
```

## Success Criteria

The AI agent must confirm:

- settlement wallet exists
- settlement wallet is active
- balance retrieval works
- wallet is operationally usable

before continuing.

---

# Phase 4 — Bank Capability Validation

## Objective

Validate bank routing compatibility.

## AI Agent Responsibilities

Run:

```bash
php artisan constellation:supported-banks
```

The AI agent should compare:

```text
Netbank bank code format
vs
Paynamics bank identifier format
```

The AI agent should create or validate:

```php
config('constellation.bank_map')
```

ONLY if necessary.

## Important Rule

The AI agent should NOT rewrite payout payload structures unless mapping incompatibility is confirmed.

---

# Phase 5 — Manual Cash-Out Validation

## Objective

Validate isolated payout capability.

## AI Agent Responsibilities

Execute ONLY a minimal payout.

Recommended amount:

```text
PHP 10
```

Use:

```bash
php artisan constellation:cash-out-nr
```

or equivalent existing command.

## Important Rule

The AI agent should validate:

```text
single isolated payout first
```

before attempting lifecycle scenarios.

## Success Criteria

The AI agent must confirm:

- payout accepted
- request ID generated
- provider transaction ID generated
- provider status retrievable

before continuing.

---

# Phase 6 — Reconciliation Validation

## Objective

Validate status retrieval and replay capability.

## AI Agent Responsibilities

Run:

```bash
php artisan constellation:transaction {requestId}
```

and/or:

```bash
php artisan constellation:cash-out-status {requestId}
```

Validate:

- pending states
- settled states
- failed states
- replayability

## Important Rule

The AI agent should NOT yet execute lifecycle runtime scenarios.

---

# Phase 7 — emi-core Provider Parity Validation

## Objective

Confirm Paynamics satisfies the existing emi-core contracts.

## AI Agent Responsibilities

Verify:

```php
LBHurtado\EmiCore\Contracts\PayoutProvider
```

compatibility.

The AI agent should confirm:

- disburse()
- checkStatus()
- fee retrieval
- payout normalization
- payout result normalization

operate consistently with Netbank behavior.

## Important Rule

The AI agent should NOT redesign emi-core contracts.

---

# Phase 8 — x-change Provider Binding

## Objective

Switch x-change payout orchestration from Netbank to Paynamics.

## AI Agent Responsibilities

Bind:

```php
ConstellationPayoutProvider
```

as the active payout provider.

The AI agent should preserve:

```text
all existing lifecycle orchestration logic
```

---

# Phase 9 — Smoke Lifecycle Validation

## Objective

Validate minimal lifecycle compatibility.

## AI Agent Responsibilities

Run ONLY:

```bash
php artisan xchange:lifecycle:run basic_cash \
    --timeout=1 \
    --poll=1 \
    --max-polls=1 \
    --accept-pending \
    --json
```

## Important Rule

The AI agent should NOT begin with divisible/open scenarios.

---

# Phase 10 — Advanced Lifecycle Validation

## Objective

Validate operational parity with Netbank.

## AI Agent Responsibilities

Run:

```bash
php artisan xchange:lifecycle:run divisible_open_three_slices_enforced_interval \
    --timeout=1 \
    --poll=1 \
    --accept-pending \
    --json
```

Validate:

- sequential payouts
- provider references
- reconciliation persistence
- pending-state handling
- settlement continuity

---

# Critical AI Agent Constraints

## The AI Agent MUST NOT

- endlessly retry failed payouts
- spam provider APIs
- brute-force bank mappings
- redesign runtime orchestration
- rewrite lifecycle engine
- rewrite emi-core contracts
- rewrite payout normalization
- generate random scenario structures

---

# Recommended AI Agent Retry Policy

## Maximum Retry Philosophy

The AI agent should:

```text
FAIL FAST
ANALYZE
THEN ADJUST
```

NOT:

```text
blind retry loops
```

## Recommended Limits

| Operation | Max Attempts |
|---|---|
| connectivity | 3 |
| wallet lookup | 3 |
| payout | 2 |
| lifecycle scenario | 2 |

---

# Required AI Agent Logging Discipline

The AI agent should preserve and analyze:

- provider request payloads
- provider responses
- request IDs
- transaction IDs
- payout references
- pending states
- settlement states
- reconciliation logs

before modifying code.

---

# Lifecycle Runtime Philosophy

The lifecycle runtime exists to validate:

```text
real operational behavior
```

NOT merely:

```text
unit-level correctness
```

The AI agent should therefore prioritize:

- operational determinism
- provider continuity
- replayability
- reconciliation stability
- payout consistency

over architectural experimentation.

---

# Final Operational Goal

The operational success criteria are:

```text
Paynamics lifecycle scenarios behave operationally equivalent to Netbank lifecycle scenarios
```

for:

- payout orchestration
- reconciliation
- pending-state handling
- settlement continuity
- lifecycle runtime execution

using:

```text
centralized settlement wallet orchestration
```

without requiring:

- external wallet federation
- per-user wallet ownership
- hybrid balance authorities
- delegated wallet orchestration

at this stage.

---

# Future Expansion (Out of Scope)

The following are intentionally deferred:

- external wallet authorities
- provider-owned user wallets
- federated balances
- hybrid wallet orchestration
- delegated settlement authorities
- cross-provider wallet routing
- programmable custody models

These may be implemented in future lifecycle/runtime revisions.

---
