# Lifecycle Scenario Catalog

## Purpose

The Lifecycle Scenario Catalog defines the canonical inventory of executable lifecycle behaviors supported by the x-change runtime.

A lifecycle scenario represents a deterministic execution flow that exercises one or more transaction lifecycle capabilities such as:

- voucher issuance
- redemption
- withdrawal
- collectible payments
- divisible claims
- settlement envelope evaluation
- provider polling
- reconciliation
- attestation
- operational validation

The catalog serves as:

- a developer reference
- an operational validation inventory
- a partner certification reference
- a deployment verification guide
- a runtime governance registry

---

# Runtime Architecture Context

Lifecycle scenarios are executed through the:

```text
Lifecycle Scenario Runtime
```

which includes:

```text
LifecycleScenarioEngine
ScenarioRunnerResolver
ScenarioRunnerContract
Scenario Groups
LifecycleScenarioRepository
```

Scenarios may be executed individually:

```bash
php artisan xchange:lifecycle:run basic_cash
```

or as grouped operational validations:

```bash
php artisan xchange:lifecycle:run-group smoke
```

---

# Scenario Metadata Model

Every lifecycle scenario may define the following metadata:

| Field | Description |
|---|---|
| key | Unique scenario identifier |
| label | Human-readable scenario name |
| category | Operational grouping classification |
| mode | Runtime execution mode |
| tags | Searchable capability markers |
| risk | Operational risk classification |
| description | Business and technical behavior summary |

---

# Scenario Categories

| Category | Purpose |
|---|---|
| smoke | Lightweight operational validation |
| contract | Business rule enforcement |
| provider | Provider integration validation |
| settlement | Settlement envelope validation |
| reconciliation | Failure and recovery workflows |
| partner | Partner onboarding and certification |
| regression | Broader release validation |

---

# Scenario Modes

| Mode | Meaning |
|---|---|
| default | Single-claim lifecycle execution |
| sequential_claims | Multiple claims against one voucher |
| settlement_envelope_evaluation | Settlement readiness evaluation |
| settlement_three_party_flow | Multi-party settlement orchestration |

---

# Risk Classification

| Risk | Meaning |
|---|---|
| low | Lightweight synthetic or local validation |
| medium | Exercises real lifecycle orchestration |
| high | Exercises settlement, provider, or reconciliation behaviors |

---

# Canonical Scenario Catalog

## Smoke Scenarios

### `basic_cash`

| Field | Value |
|---|---|
| Category | smoke |
| Mode | default |
| Risk | low |

Basic voucher issuance and redemption lifecycle validation.

Capabilities exercised:

- voucher generation
- claim preparation
- claim execution
- wallet lifecycle orchestration

---

### `basic_cash_no_claim`

| Field | Value |
|---|---|
| Category | smoke |
| Mode | default |
| Risk | low |

Voucher issuance validation without executing redemption claims.

Used primarily for:

- CI/CD validation
- deployment smoke testing
- orchestration verification

Capabilities exercised:

- voucher generation
- runtime bootstrap
- scenario orchestration
- estimate generation

---

### `wallet_debit_smoke`

| Field | Value |
|---|---|
| Category | smoke |
| Mode | default |
| Risk | medium |

Validates wallet debit orchestration during voucher execution.

Capabilities exercised:

- wallet balance validation
- debit orchestration
- lifecycle transaction recording

---

## Contract Scenarios

### `secret_required`

| Field | Value |
|---|---|
| Category | contract |
| Mode | default |
| Risk | medium |

Validates redemption secret enforcement.

Capabilities exercised:

- secret validation
- redemption gating
- rejection handling

---

### `mobile_locked_contract`

| Field | Value |
|---|---|
| Category | contract |
| Mode | default |
| Risk | medium |

Restricts redemption to a predefined mobile number.

Capabilities exercised:

- claimant identity restriction
- mobile validation
- rejection flows

---

### `otp_required_contract`

| Field | Value |
|---|---|
| Category | contract |
| Mode | default |
| Risk | medium |

Requires OTP verification before redemption.

Capabilities exercised:

- OTP gating
- challenge-response validation
- redemption authorization

---

## Sequential Claim Scenarios

### `divisible_open_three_slices`

| Field | Value |
|---|---|
| Category | contract |
| Mode | sequential_claims |
| Risk | medium |

Validates divisible voucher claims across multiple redemption slices.

Capabilities exercised:

- divisible claims
- remaining balance tracking
- sequential claim execution
- aggregate lifecycle accounting

---

### `divisible_open_three_slices_enforced_interval`

| Field | Value |
|---|---|
| Category | contract |
| Mode | sequential_claims |
| Risk | medium |

Validates sequential claims with enforced redemption intervals.

Capabilities exercised:

- time-based redemption gating
- sequential claims
- runtime interval validation

---

## Collectible Scenarios

### `collectible_basic_payment`

| Field | Value |
|---|---|
| Category | provider |
| Mode | sequential_claims |
| Risk | medium |

Validates collectible voucher payment lifecycle.

Capabilities exercised:

- collectible payments
- incremental settlement
- payment aggregation
- collectible flow resolution

---

## Settlement Scenarios

### `settlement_philhealth_bst_blocked`

| Field | Value |
|---|---|
| Category | settlement |
| Mode | settlement_envelope_evaluation |
| Risk | high |

Validates settlement readiness failure conditions.

Capabilities exercised:

- settlement envelope evaluation
- readiness validation
- missing evidence detection

---

### `settlement_philhealth_bst_three_party`

| Field | Value |
|---|---|
| Category | settlement |
| Mode | settlement_three_party_flow |
| Risk | high |

Validates full three-party settlement orchestration.

Capabilities exercised:

- patient attestation
- provider participation
- settlement envelope persistence
- readiness completion
- settlement lifecycle orchestration

---

## Reconciliation Scenarios

### `provider_pending_reconciliation`

| Field | Value |
|---|---|
| Category | reconciliation |
| Mode | default |
| Risk | high |

Validates pending provider outcomes and reconciliation handling.

Capabilities exercised:

- provider polling
- pending resolution
- reconciliation state tracking

---

### `provider_failed_reconciliation`

| Field | Value |
|---|---|
| Category | reconciliation |
| Mode | default |
| Risk | high |

Validates failed provider outcomes and operational recovery paths.

Capabilities exercised:

- provider failure handling
- reconciliation persistence
- lifecycle recovery orchestration

---

# Operational Usage

## Run Single Scenario

```bash
php artisan xchange:lifecycle:run basic_cash
```

---

## Run Scenario Group

```bash
php artisan xchange:lifecycle:run-group smoke
```

---

## Run Without Claims

```bash
php artisan xchange:lifecycle:run-group smoke --no-claim
```

Useful for:

- deployment checks
- orchestration validation
- CI/CD pipelines

---

## Run JSON Output

```bash
php artisan xchange:lifecycle:run-group smoke --json
```

Useful for:

- automated pipelines
- operational dashboards
- partner integrations
- machine-readable validation

---

# Scenario Governance

Lifecycle scenarios are governed through:

```text
LifecycleScenarioRepository
LifecycleScenarioGroupRepository
```

which provide:

- metadata normalization
- category grouping
- tag filtering
- runtime discovery
- operational grouping

---

# Future Direction

The lifecycle runtime is designed to evolve into a fully programmable operational validation framework.

Future capabilities may include:

- scenario auto-documentation
- scenario replay
- failure injection
- provider simulation
- settlement replay
- operational certification suites
- sandbox certification workflows
- CI/CD operational gating
- deployment attestation
- automated reconciliation validation

---

# Related Documentation

| Document | Purpose |
|---|---|
| `docs/lifecycle-scenarios/catalog.md` | Canonical lifecycle scenario inventory |
| `docs/lifecycle-scenarios/capability-matrix.md` | Capability and combination coverage |
| `docs/lifecycle-scenarios/taxonomy.md` | Lifecycle classification and runtime model |
| `docs/lifecycle-scenarios/composition-guide.md` | Developer guide for composing scenarios |
| `docs/lifecycle-scenarios/settlement-catalog.md` | Settlement-specific lifecycle scenarios |
| `docs/lifecycle-scenarios/provider-catalog.md` | Provider integration and reconciliation scenarios |
| `docs/lifecycle-scenarios/demo-scenarios.md` | Operationally safe and presentation-oriented scenarios |
| `docs/lifecycle-scenario-runtime.md` | Runtime architecture and execution engine |
| `docs/operations/pre-deployment-checks.md` | Operational validation before deployment |
| `docs/operations/provider-regression-testing.md` | Provider regression workflows |
| `docs/operations/bank-sandbox-validation.md` | Partner certification and sandbox validation |

---
