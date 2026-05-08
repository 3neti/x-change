# Lifecycle Capability Matrix

## Purpose

The Lifecycle Capability Matrix defines the executable business and operational capabilities supported by the x-change Lifecycle Scenario Runtime.

Unlike the Lifecycle Scenario Catalog, which inventories concrete executable scenarios, this document focuses on:

- business capabilities
- operational behaviors
- runtime combinations
- deployment validation coverage
- certification scope
- orchestration semantics

This document serves as:

- a technical capability inventory
- a licensing scope reference
- a deployment validation checklist
- a partner certification guide
- a business feature map
- an operational readiness matrix

---

# Capability Domains

The lifecycle runtime supports capabilities across multiple operational domains.

| Domain | Description |
|---|---|
| Voucher Lifecycle | Voucher issuance and redemption |
| Claim Lifecycle | Claim execution and orchestration |
| Contract Enforcement | Runtime validation constraints |
| Wallet Orchestration | Wallet debits and balance validation |
| Sequential Claims | Multi-claim lifecycle execution |
| Collectible Payments | Incremental collectible settlement |
| Provider Integration | Payout provider orchestration |
| Settlement Orchestration | Multi-party settlement lifecycle |
| Reconciliation | Failure and recovery workflows |
| Operational Validation | Deployment and certification runtime |
| Runtime Governance | Metadata and scenario governance |

---

# Core Capability Matrix

| Capability | Supported | Scenario Coverage |
|---|---|---|
| Voucher issuance | yes | `basic_cash` |
| Voucher redemption | yes | `basic_cash` |
| Voucher generation without claim | yes | `basic_cash_no_claim` |
| Wallet debit orchestration | yes | `wallet_debit_smoke` |
| Claim execution | yes | `basic_cash` |
| Claim rejection handling | yes | `secret_required` |
| Secret validation | yes | `secret_required` |
| Mobile locking | yes | `mobile_locked_contract` |
| OTP validation | yes | `otp_required_contract` |
| Sequential claims | yes | `divisible_open_three_slices` |
| Partial redemption | yes | `divisible_open_three_slices` |
| Divisible voucher lifecycle | yes | `divisible_open_three_slices` |
| Time-gated redemption | yes | `divisible_open_three_slices_enforced_interval` |
| Collectible payments | yes | `collectible_basic_payment` |
| Incremental collectible settlement | yes | `collectible_basic_payment` |
| Provider polling | yes | `provider_pending_reconciliation` |
| Pending provider handling | yes | `provider_pending_reconciliation` |
| Failed provider handling | yes | `provider_failed_reconciliation` |
| Reconciliation workflows | yes | `provider_failed_reconciliation` |
| Settlement envelope readiness | yes | `settlement_philhealth_bst_blocked` |
| Settlement envelope completion | yes | `settlement_philhealth_bst_three_party` |
| Three-party settlement | yes | `settlement_philhealth_bst_three_party` |
| Attestation workflows | yes | `settlement_philhealth_bst_three_party` |
| Runtime metadata governance | yes | all scenarios |
| Scenario grouping | yes | all groups |
| JSON operational output | yes | all runtime executions |
| Operational deployment validation | yes | `smoke` group |
| Partner certification | yes | `partner-certification` group |

---

# Combination Matrix

The lifecycle runtime supports combinatorial execution of multiple transaction constraints and lifecycle behaviors.

| Combination | Supported | Scenario Coverage |
|---|---|---|
| OTP + redemption | yes | `otp_required_contract` |
| Mobile lock + redemption | yes | `mobile_locked_contract` |
| Sequential claims + divisible vouchers | yes | `divisible_open_three_slices` |
| Sequential claims + enforced intervals | yes | `divisible_open_three_slices_enforced_interval` |
| Collectible flows + sequential claims | yes | `collectible_basic_payment` |
| Provider polling + reconciliation | yes | `provider_pending_reconciliation` |
| Settlement + attestation | yes | `settlement_philhealth_bst_three_party` |
| Settlement + readiness evaluation | yes | `settlement_philhealth_bst_blocked` |
| Provider failures + operational recovery | yes | `provider_failed_reconciliation` |
| Deployment validation + JSON reporting | yes | all runtime groups |

---

# Runtime Capability Modes

Lifecycle capabilities are orchestrated through runtime execution modes.

| Mode | Operational Meaning |
|---|---|
| `default` | Single-claim lifecycle execution |
| `sequential_claims` | Multiple claims against a voucher |
| `settlement_envelope_evaluation` | Settlement readiness validation |
| `settlement_three_party_flow` | Multi-party settlement orchestration |

---

# Operational Capability Groups

Capabilities may be operationally grouped for deployment and certification workflows.

| Group | Operational Purpose |
|---|---|
| `smoke` | Lightweight orchestration validation |
| `contract` | Runtime rule enforcement validation |
| `provider` | Provider integration verification |
| `settlement` | Settlement lifecycle validation |
| `reconciliation` | Recovery and reconciliation validation |
| `partner-certification` | Partner onboarding validation |
| `pre-deployment` | Deployment readiness checks |
| `post-deployment` | Post-release verification |
| `demo` | Presentation-safe execution flows |

---

# Settlement Capabilities

Settlement capabilities represent advanced multi-party orchestration flows.

| Capability | Supported | Scenario Coverage |
|---|---|---|
| Settlement readiness evaluation | yes | `settlement_philhealth_bst_blocked` |
| Settlement evidence validation | yes | `settlement_philhealth_bst_blocked` |
| Multi-party attestation | yes | `settlement_philhealth_bst_three_party` |
| Settlement envelope persistence | yes | `settlement_philhealth_bst_three_party` |
| Settlement orchestration | yes | `settlement_philhealth_bst_three_party` |
| Settlement completion lifecycle | yes | `settlement_philhealth_bst_three_party` |

---

# Provider Capabilities

Provider capabilities govern payout provider integration behavior.

| Capability | Supported | Scenario Coverage |
|---|---|---|
| Provider request orchestration | yes | `basic_cash` |
| Provider polling | yes | `provider_pending_reconciliation` |
| Provider timeout handling | planned | future |
| Provider pending lifecycle | yes | `provider_pending_reconciliation` |
| Provider reconciliation | yes | `provider_failed_reconciliation` |
| Provider recovery workflows | yes | `provider_failed_reconciliation` |
| Provider operational replay | planned | future |

---

# Collectible Capabilities

Collectible capabilities support incremental payment and redemption flows.

| Capability | Supported | Scenario Coverage |
|---|---|---|
| Collectible payment orchestration | yes | `collectible_basic_payment` |
| Incremental collectible settlement | yes | `collectible_basic_payment` |
| Multi-claim collectible lifecycle | yes | `collectible_basic_payment` |
| Partial collectible payment tracking | yes | `collectible_basic_payment` |

---

# Deployment Validation Mapping

Lifecycle capabilities may be mapped directly into deployment validation workflows.

| Validation Objective | Capability Coverage |
|---|---|
| Voucher issuance validation | `basic_cash_no_claim` |
| Claim orchestration validation | `basic_cash` |
| Wallet orchestration validation | `wallet_debit_smoke` |
| Sequential claim validation | `divisible_open_three_slices` |
| Settlement readiness validation | `settlement_philhealth_bst_blocked` |
| Provider reconciliation validation | `provider_failed_reconciliation` |

---

# Certification Mapping

The lifecycle runtime is designed to support operational and institutional certification workflows.

| Certification Objective | Capability Coverage |
|---|---|
| Partner onboarding | `partner-certification` |
| Bank sandbox validation | settlement + provider capabilities |
| Deployment readiness | `pre-deployment` |
| Regression verification | reconciliation + provider groups |
| Operational continuity validation | smoke + provider + settlement groups |

---

# Runtime Governance Capabilities

The lifecycle runtime includes metadata governance capabilities.

| Governance Capability | Supported |
|---|---|
| Scenario metadata normalization | yes |
| Scenario grouping | yes |
| Tag-based grouping | yes |
| Category grouping | yes |
| Runtime mode resolution | yes |
| Operational JSON output | yes |
| Aggregated group execution | yes |
| Stop-on-failure execution | yes |
| Runtime capability discovery | planned |
| Runtime auto-documentation | planned |

---

# Future Capability Expansion

The lifecycle runtime is intentionally designed to evolve into a programmable operational validation framework.

Planned future capabilities include:

- provider simulation
- webhook replay
- operational replay
- failure injection
- attestation chains
- blockchain settlement anchoring
- operational certification suites
- CI/CD deployment gates
- runtime capability auto-discovery
- automatic capability matrix generation
- scenario inheritance and composition
- dynamic scenario templating

---

# Related Documentation

| Document | Purpose |
|---|---|
| `docs/lifecycle-scenarios/catalog.md` | Canonical lifecycle scenario inventory |
| `docs/lifecycle-scenarios/taxonomy.md` | Lifecycle classification and runtime model |
| `docs/lifecycle-scenarios/composition-guide.md` | Developer guide for composing scenarios |
| `docs/lifecycle-scenarios/settlement-catalog.md` | Settlement-specific lifecycle scenarios |
| `docs/lifecycle-scenarios/provider-catalog.md` | Provider integration and reconciliation scenarios |
| `docs/lifecycle-scenario-runtime.md` | Runtime architecture and execution engine |

---
