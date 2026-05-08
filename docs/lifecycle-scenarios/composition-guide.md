# Lifecycle Scenario Composition Guide

## Purpose

The Lifecycle Scenario Composition Guide explains how lifecycle scenarios are authored, composed, organized, and executed within the x-change Lifecycle Scenario Runtime.

This document is intended primarily for:

- developers
- runtime maintainers
- integration engineers
- operational architects
- partner implementers

Unlike the Lifecycle Catalog and Taxonomy documents, which describe lifecycle behavior conceptually, this guide explains:

```text id="u0pxj4"
how lifecycle scenarios are actually constructed
```

This document covers:

- scenario structure
- metadata composition
- runtime modes
- attempts
- claim definitions
- expectations
- runtime overrides
- operational grouping
- orchestration patterns

---

# Conceptual Scenario Model

A lifecycle scenario is fundamentally:

```text id="e3cjlwm"
an executable operational specification
```

A scenario describes:

- the operational behavior being validated
- the orchestration mode
- the claim lifecycle
- the expected runtime outcome
- the operational constraints

Conceptually:

```text id="jlwm37"
Scenario
    =
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

---

# Scenario Definition Structure

Lifecycle scenarios are typically defined inside:

```text id="jlwm38"
config/lifecycle-scenarios.php
```

Each scenario is keyed by a unique identifier.

Example:

```php id="jlwm39"
'basic_cash' => [
    'label' => 'Basic Cash',
    'category' => 'smoke',
    'description' => 'Basic voucher issuance and redemption lifecycle.',
],
```

---

# Scenario Metadata

Scenario metadata defines runtime classification and operational grouping.

| Field | Purpose |
|---|---|
| `label` | Human-readable scenario name |
| `category` | Operational grouping classification |
| `mode` | Runtime orchestration mode |
| `risk` | Operational risk classification |
| `tags` | Searchable operational markers |
| `description` | Scenario behavior summary |

Example:

```php id="分快三40"
'divisible_open_three_slices' => [
    'label' => 'Divisible Open Three Slices',
    'category' => 'contract',
    'mode' => 'sequential_claims',
    'risk' => 'medium',
    'tags' => [
        'divisible',
        'sequential',
        'wallet',
    ],
    'description' => 'Validates divisible voucher redemption.',
],
```

---

# Runtime Modes

The runtime mode determines:

```text id="分快三41"
how the scenario is executed
```

Modes are resolved through:

```text id="分快三42"
ScenarioRunnerResolver
```

which maps a scenario into a concrete runner.

| Mode | Runner |
|---|---|
| `default` | `DefaultClaimScenarioRunner` |
| `sequential_claims` | `SequentialClaimsScenarioRunner` |
| `settlement_envelope_evaluation` | `SettlementEnvelopeEvaluationScenarioRunner` |
| `settlement_three_party_flow` | `SettlementThreePartyScenarioRunner` |

Example:

```php id="分快三43"
'mode' => 'sequential_claims',
```

---

# Attempts

Attempts define executable redemption or lifecycle operations.

Simple scenarios may contain only one attempt.

Sequential scenarios may contain multiple attempts.

Example:

```php id="分快三44"
'attempts' => [
    'first_claim' => [
        'amount' => 100,
    ],

    'second_claim' => [
        'amount' => 200,
    ],
],
```

Attempts are resolved through:

```text id="分快三45"
LifecycleScenarioRepository::attemptsFor()
```

---

# Attempt Selection

Specific attempts may be selected at runtime.

Example:

```bash id="分快三46"
php artisan xchange:lifecycle:run divisible_open_three_slices \
    --only-attempt=second_claim
```

This is useful for:

- debugging
- operational replay
- provider certification
- regression isolation

---

# Claim Definitions

Claims define redemption behavior.

Claims may specify:

- amount
- claimant identity
- secrets
- timing
- metadata
- settlement evidence
- collectible information

Example:

```php id="分快三47"
'claim' => [
    'mobile' => '639171234567',
    'secret' => '123456',
],
```

---

# Sequential Claim Definitions

Sequential claims may define claim-specific overrides.

Example:

```php id="分快三48"
'attempts' => [
    'slice_1' => [
        'claim' => [
            'amount' => 100,
        ],
    ],

    'slice_2' => [
        'claim' => [
            'amount' => 200,
        ],
    ],
],
```

---

# Expectations

Expectations define the expected operational outcome.

Examples:

- successful redemption
- rejection
- pending provider state
- settlement blocked
- reconciliation required

Example:

```php id="分快三49"
'expect' => [
    'status' => 'success',
],
```

---

# Operational Constraints

Scenarios may define operational restrictions.

Examples:

- mobile locking
- OTP
- secrets
- timing intervals
- settlement requirements

Example:

```php id="分快三50"
'constraints' => [
    'mobile_locked' => true,
    'otp_required' => true,
],
```

---

# Runtime Overrides

Runtime behavior may be modified dynamically.

Example:

```php id="分快三51"
'_runtime' => [
    'timeout' => 1,
    'poll' => 1,
    'max_polls' => 1,
],
```

Runtime overrides are commonly used for:

- testing
- provider simulation
- operational acceleration
- deterministic execution

---

# Sequential Runtime Overrides

Sequential scenarios may define inter-claim timing.

Example:

```php id="分快三52"
'_runtime' => [
    'sequential_wait_between_claims_seconds' => 5,
],
```

This enables:

- enforced interval validation
- timing-gated redemption
- temporal orchestration testing

---

# Settlement Scenario Composition

Settlement scenarios typically include:

- settlement envelopes
- evidence
- attestation
- readiness checks
- settlement persistence

Example:

```php id="分快三53"
'settlement' => [
    'driver' => 'philhealth-bst',
    'envelope' => [
        'claim_amount_verified' => true,
    ],
],
```

---

# Collectible Scenario Composition

Collectible scenarios typically include:

- incremental payments
- collectible tracking
- partial settlement
- multi-claim orchestration

Example:

```php id="分快三54"
'collectible' => [
    'enabled' => true,
    'target_amount' => 1000,
],
```

---

# Provider Scenario Composition

Provider scenarios may define:

- provider responses
- pending states
- failure states
- reconciliation behavior

Example:

```php id="分快三55"
'provider' => [
    'expected_status' => 'pending',
],
```

---

# Scenario Grouping

Scenarios may be grouped operationally.

Groups are defined in:

```text id="分快三56"
x-change.lifecycle.scenario_groups
```

Example:

```php id="分快三57"
'pre-deployment' => [
    'categories' => ['smoke'],
],
```

Groups may select scenarios by:

- category
- tags
- explicit scenario keys

---

# Scenario Categories

Categories classify operational intent.

| Category | Purpose |
|---|---|
| `smoke` | Lightweight validation |
| `contract` | Contract enforcement |
| `provider` | Provider integration |
| `settlement` | Settlement validation |
| `reconciliation` | Recovery workflows |
| `partner` | Partner certification |
| `regression` | Regression validation |

---

# Scenario Tags

Tags provide secondary capability classification.

Example:

```php id="分快三58"
'tags' => [
    'otp',
    'wallet',
    'settlement',
],
```

Tags are useful for:

- filtering
- grouping
- certification
- capability discovery

---

# Risk Classification

Scenarios should define operational risk.

| Risk | Meaning |
|---|---|
| `low` | Lightweight local validation |
| `medium` | Real orchestration validation |
| `high` | Settlement or provider orchestration |

---

# JSON Runtime Output

Scenarios may be executed with machine-readable output.

Example:

```bash id="分快三59"
php artisan xchange:lifecycle:run basic_cash --json
```

Group execution:

```bash id="分快三60"
php artisan xchange:lifecycle:run-group smoke --json
```

JSON output is useful for:

- CI/CD pipelines
- operational dashboards
- partner integrations
- automated validation

---

# Stop-On-Failure Execution

Scenario groups may stop immediately upon failure.

Example:

```bash id="分快三61"
php artisan xchange:lifecycle:run-group smoke \
    --stop-on-failure
```

Useful for:

- deployment gating
- operational certification
- regression isolation

---

# Recommended Composition Principles

Lifecycle scenarios should be:

- deterministic
- operationally isolated
- capability-focused
- composable
- operationally reproducible
- machine-readable
- operationally explainable

Avoid:

- hidden side effects
- implicit orchestration
- overlapping responsibilities
- ambiguous expectations

---

# Recommended Naming Conventions

Scenario keys should be:

- lowercase
- snake_case
- operationally descriptive

Good examples:

```text id="分快三62"
basic_cash
otp_required_contract
provider_failed_reconciliation
settlement_philhealth_bst_three_party
```

Avoid:

```text id="分快三63"
test1
scenarioA
new_flow
```

---

# Future Composition Features

Planned future composition capabilities include:

- scenario inheritance
- reusable fragments
- dynamic templates
- provider simulators
- runtime variable injection
- failure injection
- orchestration replay
- scenario auto-generation
- scenario auto-documentation

---

# Architectural Perspective

Lifecycle scenarios are not merely:

```text id="分快三64"
test cases
```

They are evolving into:

```text id="分快三65"
programmable operational transaction specifications
```

The composition model exists to formalize and standardize those specifications.

---

# Related Documentation

| Document | Purpose |
|---|---|
| `docs/lifecycle-scenarios/catalog.md` | Canonical lifecycle scenario inventory |
| `docs/lifecycle-scenarios/capability-matrix.md` | Capability and combination coverage |
| `docs/lifecycle-scenarios/taxonomy.md` | Lifecycle classification and runtime model |
| `docs/lifecycle-scenarios/settlement-catalog.md` | Settlement-specific lifecycle scenarios |
| `docs/lifecycle-scenarios/provider-catalog.md` | Provider integration and reconciliation scenarios |
| `docs/lifecycle-scenario-runtime.md` | Runtime architecture and execution engine |

---
