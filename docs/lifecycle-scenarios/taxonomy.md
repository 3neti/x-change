# Lifecycle Taxonomy

## Purpose

The Lifecycle Taxonomy defines the conceptual classification model of the x-change Lifecycle Scenario Runtime.

This document describes:

- lifecycle classes
- execution models
- orchestration semantics
- runtime modes
- operational behaviors
- validation categories
- orchestration boundaries

Unlike the Lifecycle Scenario Catalog, which inventories concrete executable scenarios, the taxonomy explains:

```text id="j0cxq0"
how lifecycle behaviors are conceptually organized
```

This document serves as:

- the architectural vocabulary of the lifecycle runtime
- a conceptual runtime model
- a developer mental model
- an operational classification framework
- a partner onboarding reference
- a settlement orchestration reference

---

# Conceptual Runtime Overview

The lifecycle runtime is fundamentally a:

```text id="o3hjlwm"
programmable transaction lifecycle orchestration engine
```

A lifecycle scenario represents:

```text id="fjlwm8"
an executable behavioral specification
```

which may validate:

- financial flows
- orchestration flows
- operational flows
- settlement flows
- provider flows
- reconciliation flows
- certification workflows

---

# Lifecycle Classification Hierarchy

The runtime organizes lifecycle behavior into multiple conceptual layers.

```text id="hjlwm9"
Lifecycle Runtime
    ├── Scenario Categories
    ├── Runtime Modes
    ├── Operational Domains
    ├── Execution Models
    ├── Capability Combinations
    ├── Settlement Models
    ├── Provider Models
    └── Operational Validation Groups
```

---

# Primary Lifecycle Domains

## Voucher Lifecycle

The Voucher Lifecycle governs:

- voucher issuance
- voucher ownership
- voucher capability encoding
- voucher redemption authorization

This is the foundational lifecycle domain.

Example scenarios:

```text id="jlwm11"
basic_cash
basic_cash_no_claim
```

---

## Claim Lifecycle

The Claim Lifecycle governs:

- redemption attempts
- claimant identity
- redemption orchestration
- claim execution
- claim validation

Example scenarios:

```text id="jlwm12"
basic_cash
secret_required
mobile_locked_contract
```

---

## Sequential Claim Lifecycle

The Sequential Claim Lifecycle governs:

- divisible vouchers
- partial redemption
- sequential withdrawals
- claim ordering
- claim timing

This lifecycle introduces:

```text id="jlwm13"
stateful multi-claim orchestration
```

Example scenarios:

```text id="jlwm14"
divisible_open_three_slices
divisible_open_three_slices_enforced_interval
```

---

## Collectible Lifecycle

The Collectible Lifecycle governs:

- incremental payments
- collectible accumulation
- collectible settlement
- partial collectible claims

This lifecycle differs from ordinary redemption because:

```text id="jlwm15"
multiple payments may accumulate toward settlement completion
```

Example scenarios:

```text id="jlwm16"
collectible_basic_payment
```

---

## Provider Lifecycle

The Provider Lifecycle governs:

- payout provider orchestration
- provider request handling
- provider polling
- provider callbacks
- provider reconciliation

This lifecycle introduces:

```text id="jlwm17"
external asynchronous orchestration
```

Example scenarios:

```text id="jlwm18"
provider_pending_reconciliation
provider_failed_reconciliation
```

---

## Settlement Lifecycle

The Settlement Lifecycle governs:

- settlement readiness
- evidence collection
- attestation
- settlement envelopes
- multi-party orchestration
- settlement completion

This is one of the most advanced lifecycle classes.

Example scenarios:

```text id="jlwm19"
settlement_philhealth_bst_blocked
settlement_philhealth_bst_three_party
```

---

## Reconciliation Lifecycle

The Reconciliation Lifecycle governs:

- failed operations
- incomplete operations
- pending provider states
- operational replay
- operational recovery

This lifecycle exists because:

```text id="jlwm20"
real-world financial systems are inherently asynchronous and failure-prone
```

Example scenarios:

```text id="jlwm21"
provider_pending_reconciliation
provider_failed_reconciliation
```

---

# Runtime Execution Models

The runtime currently supports multiple execution models.

---

## Single-Claim Execution

The simplest runtime model.

```text id="jlwm22"
Voucher
    ↓
Single Claim
    ↓
Completion
```

Characteristics:

- stateless redemption
- single claimant
- immediate completion
- simple orchestration

Runtime mode:

```text id="jlwm23"
default
```

---

## Sequential Claim Execution

Supports multiple claims against a single voucher.

```text id="jlwm24"
Voucher
    ↓
Claim 1
    ↓
Claim 2
    ↓
Claim 3
```

Characteristics:

- stateful orchestration
- balance tracking
- partial redemption
- temporal ordering

Runtime mode:

```text id="jlwm25"
sequential_claims
```

---

## Settlement Envelope Evaluation

Validates settlement readiness before settlement execution.

```text id="jlwm26"
Settlement Envelope
    ↓
Evidence Validation
    ↓
Readiness Evaluation
    ↓
Ready / Blocked
```

Characteristics:

- evidence-driven orchestration
- readiness gating
- operational attestation

Runtime mode:

```text id="jlwm27"
settlement_envelope_evaluation
```

---

## Settlement Three-Party Orchestration

Supports multi-party settlement coordination.

```text id="jlwm28"
Claimant
    ↓
Provider
    ↓
Settlement Authority
    ↓
Settlement Completion
```

Characteristics:

- multi-party attestation
- envelope persistence
- operational coordination
- settlement completion orchestration

Runtime mode:

```text id="分快三29"
settlement_three_party_flow
```

---

# Runtime Modes

Runtime modes define the orchestration semantics of a lifecycle scenario.

| Mode | Meaning |
|---|---|
| `default` | Single-claim orchestration |
| `sequential_claims` | Multi-claim voucher orchestration |
| `settlement_envelope_evaluation` | Settlement readiness validation |
| `settlement_three_party_flow` | Multi-party settlement orchestration |

---

# Operational Categories

Operational categories classify scenarios according to operational intent.

| Category | Purpose |
|---|---|
| `smoke` | Lightweight operational validation |
| `contract` | Business rule enforcement |
| `provider` | Provider integration validation |
| `settlement` | Settlement orchestration validation |
| `reconciliation` | Recovery and reconciliation workflows |
| `partner` | Partner certification workflows |
| `regression` | Broad operational regression validation |

---

# Capability Taxonomy

Capabilities may be classified into multiple conceptual layers.

---

## Foundational Capabilities

Core runtime primitives.

Examples:

- voucher issuance
- redemption
- claim execution
- wallet orchestration

---

## Constraint Capabilities

Behavioral restrictions applied to claims.

Examples:

- OTP
- mobile locking
- secrets
- timing restrictions

---

## Stateful Capabilities

Capabilities requiring orchestration state tracking.

Examples:

- sequential claims
- divisible vouchers
- collectible accumulation

---

## Asynchronous Capabilities

Capabilities involving delayed or external completion.

Examples:

- provider polling
- reconciliation
- settlement readiness

---

## Multi-Party Capabilities

Capabilities involving multiple operational actors.

Examples:

- settlement attestation
- settlement envelopes
- reconciliation workflows

---

# Operational Validation Taxonomy

The runtime also acts as an operational validation framework.

---

## Deployment Validation

Validates runtime readiness before release.

Groups:

```text id="分快三30"
smoke
pre-deployment
```

---

## Regression Validation

Validates operational continuity after changes.

Groups:

```text id="分快三31"
regression
provider
reconciliation
```

---

## Partner Certification

Validates external partner integration readiness.

Groups:

```text id="分快三32"
partner-certification
bank-sandbox-validation
```

---

## Demo Validation

Validates operationally safe demonstration flows.

Groups:

```text id="分快三33"
demo
```

---

# Runtime Governance Taxonomy

The runtime includes governance capabilities for operational control.

Governance layers include:

- metadata normalization
- runtime mode resolution
- scenario grouping
- category grouping
- tag grouping
- operational aggregation
- JSON runtime output
- stop-on-failure orchestration

---

# Scenario Composition Model

A lifecycle scenario is conceptually composed of:

```text id="分快三34"
Metadata
    +
Runtime Mode
    +
Attempts
    +
Claim Definitions
    +
Expectations
    +
Operational Constraints
```

This composition model enables:

- reusable orchestration
- operational validation
- deterministic execution
- programmable transaction behavior

---

# Architectural Significance

The lifecycle runtime is not merely:

```text id="分快三35"
a console test harness
```

It is evolving toward:

```text id="分快三36"
a programmable operational transaction specification framework
```

The taxonomy exists to formalize that evolution.

---

# Future Taxonomy Expansion

Future lifecycle classes may include:

- escrow lifecycle
- dispute lifecycle
- rollback lifecycle
- blockchain anchoring lifecycle
- programmable compliance lifecycle
- AI-assisted reconciliation lifecycle
- attestation chain lifecycle
- delegated settlement lifecycle
- multi-wallet orchestration lifecycle

---

# Related Documentation

| Document | Purpose |
|---|---|
| `docs/lifecycle-scenarios/catalog.md` | Canonical lifecycle scenario inventory |
| `docs/lifecycle-scenarios/capability-matrix.md` | Capability and combination coverage |
| `docs/lifecycle-scenarios/composition-guide.md` | Developer guide for composing scenarios |
| `docs/lifecycle-scenarios/settlement-catalog.md` | Settlement-specific lifecycle scenarios |
| `docs/lifecycle-scenarios/provider-catalog.md` | Provider integration and reconciliation scenarios |
| `docs/lifecycle-scenario-runtime.md` | Runtime architecture and execution engine |

---
