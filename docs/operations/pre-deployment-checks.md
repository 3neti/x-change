# Pre-Deployment Operational Checks

## Purpose

This document defines the recommended operational validation procedures that should be executed before deploying the x-change platform into a target environment.

The purpose of pre-deployment validation is to ensure:

- runtime readiness
- orchestration integrity
- provider availability
- settlement readiness
- operational continuity
- deployment safety

These checks are performed through the:

```text id="predeploy01"
Lifecycle Scenario Runtime
```

using executable lifecycle scenario groups.

This document is intended for:

- deployment engineers
- DevOps teams
- operational administrators
- partner integration teams
- certification personnel

---

# Deployment Validation Philosophy

Pre-deployment validation is intentionally designed to validate:

```text id="predeploy02"
operational behavior
```

rather than merely:

```text id="predeploy03"
application availability
```

Traditional deployment checks often validate:

- HTTP responses
- database connectivity
- container startup
- infrastructure availability

The lifecycle runtime instead validates:

- executable transaction behavior
- orchestration continuity
- settlement readiness
- provider integration
- runtime governance
- operational determinism

---

# Recommended Deployment Validation Flow

Recommended operational flow:

```text id="predeploy04"
Infrastructure Validation
    ↓
Application Deployment
    ↓
Database Migration
    ↓
Lifecycle Runtime Validation
    ↓
Provider Validation
    ↓
Settlement Validation
    ↓
Operational Sign-Off
```

---

# Recommended Runtime Validation Order

The recommended execution order is:

```text id="predeploy05"
1. Smoke Validation
2. Contract Validation
3. Provider Validation
4. Settlement Validation
5. Reconciliation Validation
6. Partner Validation
```

This order ensures:

- foundational runtime stability
- orchestration continuity
- settlement integrity
- provider readiness

---

# Pre-Deployment Scenario Groups

The runtime supports operational grouping through:

```text id="predeploy06"
x-change.lifecycle.scenario_groups
```

Recommended deployment groups:

| Group | Purpose |
|---|---|
| `smoke` | Lightweight orchestration validation |
| `pre-deployment` | Comprehensive deployment validation |
| `provider` | Provider integration validation |
| `settlement` | Settlement readiness validation |
| `reconciliation` | Operational recovery validation |

---

# Recommended Smoke Validation

The first deployment validation step should always be:

```bash id="predeploy07"
php artisan xchange:lifecycle:run-group smoke \
    --no-claim \
    --json
```

This validates:

- scenario repository
- runtime orchestration
- bootstrap pipeline
- voucher generation
- runtime metadata
- JSON output
- scenario grouping

without executing live claims.

---

# Why `--no-claim` Is Recommended First

The `--no-claim` option validates:

```text id="predeploy08"
runtime orchestration integrity
```

while avoiding:

- provider payouts
- asynchronous polling
- settlement execution
- operational side effects

This makes it ideal for:

- CI/CD
- deployment pipelines
- infrastructure validation
- staging verification

---

# Recommended Deployment Validation Levels

---

## Level 1 — Runtime Integrity

### Purpose

Validates core runtime orchestration.

### Recommended Command

```bash id="predeploy09"
php artisan xchange:lifecycle:run-group smoke \
    --no-claim \
    --json
```

### Validates

- scenario loading
- runtime orchestration
- bootstrap execution
- voucher generation
- runtime governance

---

## Level 2 — Contract Enforcement

### Purpose

Validates operational restrictions and behavioral rules.

### Recommended Command

```bash id="predeploy10"
php artisan xchange:lifecycle:run-group contract \
    --json
```

### Validates

- OTP
- mobile locking
- secrets
- divisible claims
- interval enforcement

---

## Level 3 — Provider Validation

### Purpose

Validates payout provider orchestration.

### Recommended Command

```bash id="predeploy11"
php artisan xchange:lifecycle:run-group provider \
    --timeout=1 \
    --poll=1 \
    --max-polls=1 \
    --json
```

### Validates

- provider connectivity
- provider polling
- provider reconciliation
- asynchronous orchestration

---

## Level 4 — Settlement Validation

### Purpose

Validates settlement readiness and orchestration.

### Recommended Command

```bash id="predeploy12"
php artisan xchange:lifecycle:run-group settlement \
    --json
```

### Validates

- settlement envelopes
- readiness evaluation
- attestation workflows
- settlement orchestration

---

## Level 5 — Reconciliation Validation

### Purpose

Validates operational recovery and replay.

### Recommended Command

```bash id="predeploy13"
php artisan xchange:lifecycle:run-group reconciliation \
    --json
```

### Validates

- provider recovery
- reconciliation persistence
- operational continuity
- recovery orchestration

---

# Recommended Operational Runtime Parameters

Recommended deployment runtime settings:

| Parameter | Recommended Value |
|---|---|
| `timeout` | `1` |
| `poll` | `1` |
| `max_polls` | `1` |
| `--json` | enabled |
| `--stop-on-failure` | enabled |

Example:

```bash id="predeploy14"
php artisan xchange:lifecycle:run-group pre-deployment \
    --timeout=1 \
    --poll=1 \
    --max-polls=1 \
    --stop-on-failure \
    --json
```

---

# Stop-On-Failure Deployment Validation

Recommended production deployments should use:

```bash id="predeploy15"
--stop-on-failure
```

This ensures:

```text id="predeploy16"
deployment validation halts immediately upon operational failure
```

This prevents:

- incomplete operational deployment
- hidden orchestration failures
- partial provider readiness
- settlement inconsistencies

---

# JSON Deployment Validation

Deployment validation should generally use:

```bash id="predeploy17"
--json
```

because machine-readable output enables:

- CI/CD integration
- deployment dashboards
- operational auditability
- automated sign-off
- deployment attestation

---

# Example JSON Deployment Flow

Example deployment pipeline step:

```bash id="predeploy18"
php artisan xchange:lifecycle:run-group pre-deployment \
    --stop-on-failure \
    --json
```

Example conceptual result:

```json id="predeploy19"
{
  "group": "pre-deployment",
  "successful": true,
  "summary": {
    "total": 12,
    "passed": 12,
    "failed": 0
  }
}
```

---

# Deployment Environment Recommendations

Deployment validation should preferably occur in:

| Environment | Recommendation |
|---|---|
| Local | yes |
| CI/CD | yes |
| Staging | strongly recommended |
| Sandbox | strongly recommended |
| Production | recommended with controlled settings |

---

# Recommended Deployment Isolation

Deployment validation should prioritize:

- isolated wallets
- synthetic providers
- deterministic polling
- controlled settlement flows
- replayable execution

Avoid:

- unrestricted production payouts
- unstable provider dependencies
- uncontrolled settlement orchestration

during deployment validation.

---

# Provider Validation Recommendations

Provider validations should:

- use short polling intervals
- minimize operational latency
- validate reconciliation capability
- verify provider connectivity
- validate timeout handling

Recommended:

```bash id="predeploy20"
--timeout=1 --poll=1 --max-polls=1
```

---

# Settlement Validation Recommendations

Settlement validation should verify:

- readiness evaluation
- evidence validation
- settlement persistence
- attestation integrity
- reconciliation continuity

Settlement validation should remain:

```text id="predeploy21"
deterministic and replayable
```

---

# Deployment Sign-Off Criteria

Deployment should generally proceed only if:

| Validation | Required |
|---|---|
| Smoke validation | yes |
| Contract validation | yes |
| Provider validation | yes |
| Settlement validation | recommended |
| Reconciliation validation | recommended |

Operational sign-off should require:

```text id="predeploy22"
zero failed lifecycle scenarios
```

unless explicitly waived.

---

# Operational Incident Prevention

Pre-deployment runtime validation helps prevent:

- broken voucher issuance
- failed claim orchestration
- provider reconciliation failures
- settlement readiness failures
- operational state corruption
- deployment regressions

---

# CI/CD Integration

Lifecycle runtime validation is intentionally designed for CI/CD integration.

Recommended CI/CD flow:

```text id="predeploy23"
Build
    ↓
Test
    ↓
Deploy
    ↓
Lifecycle Validation
    ↓
Operational Approval
```

---

# Future Deployment Validation Expansion

Future deployment validation capabilities may include:

- provider simulation
- webhook replay
- deployment attestation
- runtime replay
- automated rollback triggers
- AI-assisted operational analysis
- settlement certification
- operational scoring
- deployment health dashboards

---

# Architectural Significance

Pre-deployment lifecycle validation transforms deployment verification from:

```text id="predeploy24"
infrastructure readiness
```

into:

```text id="predeploy25"
operational transaction readiness
```

This distinction is foundational to the lifecycle runtime architecture.

---

# Related Documentation

| Document | Purpose |
|---|---|
| `docs/lifecycle-scenario-runtime.md` | Runtime architecture and execution engine |
| `docs/lifecycle-scenarios/catalog.md` | Canonical lifecycle scenario inventory |
| `docs/lifecycle-scenarios/provider-catalog.md` | Provider integration and reconciliation scenarios |
| `docs/lifecycle-scenarios/settlement-catalog.md` | Settlement-specific lifecycle scenarios |
| `docs/operations/provider-regression-testing.md` | Provider regression workflows |
| `docs/operations/bank-sandbox-validation.md` | Partner certification and sandbox validation |

---
