# Demo Lifecycle Scenarios

## Purpose

The Demo Lifecycle Scenario Catalog defines operationally safe and presentation-oriented lifecycle scenarios designed for:

- executive demonstrations
- partner presentations
- institutional briefings
- bank roadshows
- operational showcases
- sandbox demonstrations
- pilot walkthroughs
- certification previews

Unlike ordinary lifecycle validation scenarios, demo scenarios prioritize:

```text id="demo01"
predictability
clarity
speed
visual explainability
operational safety
```

This document serves as:

- a demonstration orchestration guide
- a roadshow execution reference
- a sandbox presentation catalog
- a partner showcase framework
- a controlled operational demo guide

---

# Demo Runtime Philosophy

Demo scenarios are intentionally designed to be:

- deterministic
- fast
- operationally explainable
- visually understandable
- low-risk
- presentation-friendly

Demo orchestration should avoid:

- unpredictable provider delays
- unstable asynchronous behavior
- operational ambiguity
- excessive polling
- long reconciliation flows

The goal is:

```text id="demo02"
demonstrable operational clarity
```

rather than:

```text id="demo03"
maximum operational complexity
```

---

# Demo Lifecycle Principles

All demo scenarios should prioritize:

| Principle | Meaning |
|---|---|
| Predictability | Runtime behavior should be deterministic |
| Visibility | Operational behavior should be explainable live |
| Speed | Runtime completion should be fast |
| Safety | Avoid risky operational states |
| Repeatability | Demos should be replayable reliably |
| Explainability | Observers should understand the lifecycle |
| Isolation | Demos should avoid operational side effects |

---

# Demo Operational Categories

Demo scenarios may be grouped into multiple presentation categories.

| Demo Category | Purpose |
|---|---|
| Executive Demo | Simple executive-facing demonstrations |
| Technical Demo | Engineering and integration walkthroughs |
| Settlement Demo | Institutional settlement demonstrations |
| SMS Demo | SMS-based lifecycle flows |
| QR Demo | QR-oriented redemption flows |
| Collectible Demo | Incremental payment demonstrations |
| Sequential Demo | Divisible voucher demonstrations |
| Sandbox Demo | Controlled certification demonstrations |

---

# Recommended Demo Runtime Settings

Demo scenarios should typically use:

| Runtime Option | Recommended Value |
|---|---|
| `timeout` | `1` |
| `poll` | `1` |
| `max_polls` | `1` |
| `--json` | enabled |
| `--no-claim` | optional |

Example:

```bash id="demo04"
php artisan xchange:lifecycle:run-group demo \
    --timeout=1 \
    --poll=1 \
    --max-polls=1 \
    --json
```

---

# Demo Scenario Catalog

---

## `basic_cash`

### Classification

| Field | Value |
|---|---|
| Category | smoke |
| Demo Suitability | excellent |
| Risk | low |

---

### Purpose

Demonstrates the foundational voucher lifecycle:

- voucher generation
- redemption
- claim orchestration
- wallet execution

This is the recommended:

```text id="demo05"
first demonstration scenario
```

for most audiences.

---

### Demo Strengths

| Strength | Value |
|---|---|
| Fast execution | yes |
| Operational clarity | high |
| Executive explainability | high |
| Technical explainability | high |
| Low operational risk | yes |

---

### Recommended Usage

Used for:

- executive briefings
- bank roadshows
- partner onboarding
- initial demonstrations

---

### Example Runtime Execution

```bash id="demo06"
php artisan xchange:lifecycle:run basic_cash
```

---

## `basic_cash_no_claim`

### Classification

| Field | Value |
|---|---|
| Category | smoke |
| Demo Suitability | excellent |
| Risk | very low |

---

### Purpose

Demonstrates voucher orchestration without executing real claims.

This scenario is ideal for:

- infrastructure demonstrations
- CI/CD demonstrations
- orchestration walkthroughs
- operational validation previews

---

### Demo Strengths

| Strength | Value |
|---|---|
| Extremely fast | yes |
| No payout execution | yes |
| Operationally safe | yes |
| Infrastructure visibility | high |

---

### Recommended Usage

Used for:

- deployment demonstrations
- orchestration walkthroughs
- technical architecture demos
- runtime demonstrations

---

### Example Runtime Execution

```bash id="demo07"
php artisan xchange:lifecycle:run basic_cash \
    --no-claim
```

---

## `divisible_open_three_slices`

### Classification

| Field | Value |
|---|---|
| Category | contract |
| Demo Suitability | high |
| Risk | medium |

---

### Purpose

Demonstrates divisible voucher redemption using multiple sequential claims.

This scenario visually demonstrates:

- partial redemption
- balance tracking
- sequential lifecycle orchestration

---

### Demo Strengths

| Strength | Value |
|---|---|
| Visually intuitive | yes |
| Strong operational storytelling | yes |
| Demonstrates programmable transactions | yes |
| Excellent technical showcase | yes |

---

### Recommended Usage

Used for:

- programmable transaction demonstrations
- divisible payment demonstrations
- wallet orchestration demos
- technical roadshows

---

### Runtime Model

```text id="demo08"
Voucher
    ↓
Claim Slice 1
    ↓
Remaining Balance
    ↓
Claim Slice 2
    ↓
Remaining Balance
    ↓
Claim Slice 3
```

---

### Example Runtime Execution

```bash id="demo09"
php artisan xchange:lifecycle:run \
    divisible_open_three_slices \
    --json
```

---

## `collectible_basic_payment`

### Classification

| Field | Value |
|---|---|
| Category | provider |
| Demo Suitability | high |
| Risk | medium |

---

### Purpose

Demonstrates collectible payment orchestration.

This scenario is particularly useful for explaining:

- incremental settlement
- programmable payment accumulation
- collectible lifecycle orchestration

---

### Demo Strengths

| Strength | Value |
|---|---|
| Operationally intuitive | yes |
| Strong business narrative | yes |
| Excellent for institutional demos | yes |

---

### Recommended Usage

Used for:

- collectible payment demonstrations
- institutional settlement discussions
- programmable payment showcases

---

### Example Runtime Execution

```bash id="demo10"
php artisan xchange:lifecycle:run \
    collectible_basic_payment \
    --json
```

---

## `settlement_philhealth_bst_three_party`

### Classification

| Field | Value |
|---|---|
| Category | settlement |
| Demo Suitability | advanced |
| Risk | high |

---

### Purpose

Demonstrates multi-party settlement orchestration.

This scenario showcases:

- settlement envelopes
- institutional attestation
- settlement readiness
- operational coordination

---

### Demo Strengths

| Strength | Value |
|---|---|
| Architecturally impressive | yes |
| Strong institutional relevance | yes |
| Demonstrates advanced orchestration | yes |

---

### Recommended Usage

Used for:

- healthcare demonstrations
- institutional settlement presentations
- advanced technical walkthroughs
- government presentations

---

### Runtime Model

```text id="demo11"
Claimant
    ↓
Provider
    ↓
Settlement Authority
    ↓
Settlement Completion
```

---

### Operational Warning

This scenario should be used only in:

- controlled environments
- prepared demonstrations
- stable sandbox deployments

---

### Example Runtime Execution

```bash id="demo12"
php artisan xchange:lifecycle:run \
    settlement_philhealth_bst_three_party \
    --json
```

---

# Demo Scenario Groups

Recommended demo groups:

| Group | Purpose |
|---|---|
| `demo` | General demonstration workflows |
| `smoke` | Lightweight runtime demonstrations |
| `partner-certification` | Sandbox showcase demonstrations |

---

# Demo Runtime Safety

Demo environments should prioritize:

- isolated wallets
- synthetic providers
- deterministic polling
- limited payout exposure
- replayable execution
- controlled settlement flows

Recommended:

```text id="demo13"
dedicated demo environments
```

Avoid:

```text id="demo14"
production operational dependencies
```

during presentations.

---

# Recommended Demo Flow Order

For live presentations, recommended flow order:

---

## Executive Demo Flow

```text id="demo15"
1. basic_cash
2. divisible_open_three_slices
3. collectible_basic_payment
4. settlement_philhealth_bst_three_party
```

---

## Technical Demo Flow

```text id="demo16"
1. basic_cash_no_claim
2. JSON runtime output
3. sequential claims
4. provider reconciliation
5. settlement orchestration
```

---

## Institutional Demo Flow

```text id="demo17"
1. settlement readiness
2. attestation
3. settlement envelopes
4. reconciliation
5. operational replay
```

---

# Demo JSON Output

Demo scenarios should frequently use:

```bash id="demo18"
--json
```

because machine-readable output improves:

- observability
- explainability
- dashboard visualization
- partner integration understanding

Example:

```bash id="demo19"
php artisan xchange:lifecycle:run-group demo --json
```

---

# Demo Presentation Guidance

When presenting demo scenarios:

- explain the lifecycle visually
- narrate operational state transitions
- explain orchestration boundaries
- explain runtime modes
- explain deterministic validation
- emphasize programmable transaction behavior

Recommended framing:

```text id="demo20"
"The instruction is the transaction."
```

---

# Recommended Demo Design Principles

Demo scenarios should prioritize:

- clarity over complexity
- visibility over optimization
- explainability over abstraction
- operational stability over realism

Avoid:

- excessive reconciliation delays
- unstable provider orchestration
- unpredictable polling
- ambiguous runtime behavior

---

# Future Demo Lifecycle Expansion

Future demo-oriented capabilities may include:

- visual orchestration dashboards
- live settlement visualizers
- QR orchestration demos
- SMS replay demos
- provider simulation dashboards
- animated lifecycle playback
- AI-assisted operational narration
- browser-based runtime replay

---

# Architectural Significance

Demo scenarios are strategically important because they transform:

```text id="demo21"
abstract orchestration
```

into:

```text id="demo22"
observable operational behavior
```

This is critical for:

- licensing discussions
- institutional onboarding
- technical adoption
- executive understanding
- operational trust-building

---

# Related Documentation

| Document | Purpose |
|---|---|
| `docs/lifecycle-scenarios/catalog.md` | Canonical lifecycle scenario inventory |
| `docs/lifecycle-scenarios/capability-matrix.md` | Capability and combination coverage |
| `docs/lifecycle-scenarios/taxonomy.md` | Lifecycle classification and runtime model |
| `docs/lifecycle-scenarios/composition-guide.md` | Developer guide for composing scenarios |
| `docs/lifecycle-scenarios/settlement-catalog.md` | Settlement-specific lifecycle scenarios |
| `docs/lifecycle-scenario-runtime.md` | Runtime architecture and execution engine |

---
