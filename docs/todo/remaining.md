# Remaining Activities Checklist
## Lifecycle Scenario Runtime Plan
## Scenario Metadata Governance Plan

# A. Remaining Runtime Features

## A1. Scenario Group HTTP Runtime Endpoint

### Goal

Expose scenario-group execution through HTTP.

### Planned Endpoint

```text id="m90d4g"
POST /api/x/v1/lifecycle/scenario-groups/{group}/run
```

### Remaining Work

- Create route
- Create controller adapter
- Wire `LifecycleScenarioGroupRunner`
- Support:
    - `--json`
    - `stop_on_failure`
    - runtime options
- Add API tests
- Add response schema docs

---

## A2. Lifecycle Run Persistence

### Goal

Persist runtime executions for:

- dashboards
- certification
- auditability
- operational replay
- CI artifacts

### Planned Models

```text id="x7nn8t"
LifecycleScenarioRun
LifecycleScenarioRunAttempt
```

### Remaining Work

- Create migrations
- Create models
- Persist:
    - scenario
    - category
    - group
    - status
    - payload
    - runner
    - duration
    - timestamps
    - artifacts
- Persist attempt summaries
- Persist reconciliation data
- Persist settlement readiness data
- Add persistence service
- Add runtime hooks
- Add tests

---

## A3. Structured Execution Reports

### Goal

Formalize runtime execution reports.

### Planned Shape

```php id="f4hztp"
[
    'scenario' => '...',
    'category' => '...',
    'runner' => '...',
    'status' => '...',
    'duration_ms' => 123,
    'started_at' => '...',
    'finished_at' => '...',
    'artifacts' => [],
]
```

### Remaining Work

- Define DTO/data object
- Add duration tracking
- Add environment tracking
- Add runner metadata
- Add artifact extraction
- Add reconciliation artifact support
- Add settlement artifact support
- Add export formatting

---

## A4. Runtime Run Lookup API

### Goal

Expose persisted runtime runs.

### Planned Endpoints

```text id="3t7zlf"
GET /api/x/v1/lifecycle/runs/{run_id}
GET /api/x/v1/lifecycle/scenarios
GET /api/x/v1/lifecycle/scenarios/{scenario}
```

### Remaining Work

- Add controllers
- Add resources/transformers
- Add API tests
- Add auth guards
- Add environment guards

---

## A5. Environment Guards

### Goal

Prevent dangerous runtime execution in production.

### Remaining Work

- Add config guards
- Add production protection
- Add runtime confirmation rules
- Add audit logging
- Add API middleware
- Add allow-list support

---

## A6. Audit Logging

### Goal

Track runtime execution activity.

### Remaining Work

- Add audit hooks
- Log:
    - runtime execution
    - scenario groups
    - settlement execution
    - replay execution
- Add correlation IDs
- Add environment metadata

---

## A7. Async Runtime Execution

### Goal

Support queued lifecycle execution.

### Remaining Work

- Add queued runner support
- Add async status tracking
- Add polling endpoint
- Add queue configuration
- Add timeout handling
- Add retry semantics

---

## A8. Downloadable Certification Reports

### Goal

Generate exportable operational certification artifacts.

### Remaining Work

- Add report generator
- Add PDF/JSON export
- Add certification summary
- Add partner certification artifact
- Add operational signatures
- Add settlement readiness export

---

# B. Remaining Operational Documentation

## B1. Post-Deployment Checks

### File

```text id="g7ax2k"
docs/operations/post-deployment-checks.md
```

### Remaining Work

- Draft operational post-deploy validation
- Add runtime examples
- Add API examples
- Add expected outputs

---

## B2. Partner Certification

### File

```text id="i4yv4j"
docs/operations/partner-certification.md
```

### Remaining Work

- Draft certification workflow
- Define certification groups
- Define certification process
- Define certification outputs

---

## B3. Incident Reproduction

### File

```text id="d7z8hz"
docs/operations/incident-reproduction.md
```

### Remaining Work

- Draft replay workflow
- Define regression reproduction process
- Define operational replay strategy

---

## B4. Demo Automation

### File

```text id="q2w1na"
docs/operations/demo-automation.md
```

### Remaining Work

- Draft demo orchestration workflows
- Add CLI examples
- Add API examples
- Add recommended demo groups

---

# C. Remaining Scenario Governance Improvements

## C1. Scenario Metadata Completion

### Goal

Ensure all scenarios contain canonical metadata.

### Remaining Work

- Review all scenarios
- Ensure:
    - category
    - risk
    - tags
    - description
    - label
- Normalize naming consistency

---

## C2. Optional Metadata Enums

### Possible Files

```text id="3ib3gm"
LifecycleScenarioCategory.php
LifecycleScenarioRisk.php
```

### Remaining Work

- Decide if enums are needed
- Add enum support if desired
- Update repository normalization

---

## C3. Scenario Capability Discovery

### Goal

Allow runtime capability introspection.

### Remaining Work

- Add capability registry
- Add capability metadata
- Add capability lookup API
- Add capability reporting

---

# D. Remaining Runtime Evolution Features

## D1. Scenario Replay Framework

### Goal

Replay runtime executions.

### Remaining Work

- Persist runtime payloads
- Add replay API
- Add replay CLI
- Add replay reports

---

## D2. Scenario Inheritance / Templates

### Goal

Reduce duplicated scenario configuration.

### Remaining Work

- Define inheritance model
- Add merge strategy
- Add template support
- Add validation rules

---

## D3. Runtime Visualization Layer

### Goal

Visualize lifecycle orchestration.

### Remaining Work

- Add timeline renderer
- Add orchestration graph
- Add settlement visualizer
- Add reconciliation dashboard

---

## D4. CI/CD Runtime Integration

### Goal

Formalize deployment gating.

### Remaining Work

- Add CI examples
- Add GitHub Actions examples
- Add deployment gates
- Add runtime health scoring

---

# E. Remaining Strategic / Enterprise Features

## E1. Operational Certification Framework

### Goal

Turn lifecycle runtime into formal certification engine.

### Remaining Work

- Certification scoring
- Certification reports
- Certification artifacts
- Partner sign-off workflows
- Settlement attestation export

---

## E2. Multi-Partner Sandbox Support

### Goal

Support partner-scoped lifecycle execution.

### Remaining Work

- Partner-scoped configuration
- Partner isolation
- Partner runtime metadata
- Partner certification persistence

---

## E3. Operational Replay and Incident Archive

### Goal

Turn incidents into executable lifecycle assets.

### Remaining Work

- Incident-to-scenario workflow
- Replay archive
- Regression archive
- Operational recovery library

---

# F. Recommended Next Execution Order

## Recommended Next Tasks

```text id="jlwm92"
1. Draft remaining operational docs
2. Add scenario-group HTTP endpoint
3. Add lifecycle run persistence
4. Add structured execution reports
5. Add certification report exports
6. Add replay framework
7. Add visualization/dashboard layer
```

---

# G. Major Architectural Milestone Already Achieved

Already completed:

```text id="jlwm93"
Lifecycle Scenario Runtime is now a first-class operational subsystem.
```

You already have:

- runtime engine
- orchestration runners
- scenario governance
- runtime groups
- settlement orchestration
- provider reconciliation
- deployment validation
- certification-oriented workflows
- operational documentation

This is already far beyond a simple testing framework.

---
