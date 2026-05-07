# Lifecycle Scenario Runtime — Implementation Plan

## Purpose

Create a first-class subsystem called **Lifecycle Scenario Runtime**.

Its job is to execute, verify, and certify end-to-end x-change flows across:

- voucher
- wallet
- contact
- EMI / disbursement
- settlement envelope
- reconciliation
- pricing
- onboarding
- partner integrations

This subsystem turns lifecycle scenarios into executable operational assets.

Core principle:

> No package integration is complete until it has a named lifecycle scenario proving it works.

---

# 1. Target Architecture

Move the lifecycle runtime out of `Console/Commands` and into a dedicated domain subsystem.

## Target Shape

```text
src/Lifecycle/Scenarios
    LifecycleScenarioEngine.php
    LifecycleScenarioRunOptions.php
    LifecycleScenarioEngineResult.php
    LifecycleScenarioRepository.php
    LifecycleScenarioBootstrapper.php
    LifecycleScenarioBootstrapResult.php

src/Lifecycle/Runners
    ScenarioRunnerContract.php
    ScenarioRunContext.php
    ScenarioRunResult.php
    ScenarioRunnerResolver.php
    ScenarioRunnerRegistry.php
    ScenarioRunnerResolution.php

    DefaultClaimScenarioRunner.php
    SequentialClaimsScenarioRunner.php
    SettlementEnvelopeEvaluationScenarioRunner.php
    SettlementThreePartyScenarioRunner.php

src/Lifecycle/Runners/Support
    LifecycleClaimAttemptEvaluator.php
    LifecycleClaimResultNormalizer.php
    LifecycleDisbursementPoller.php
    LifecycleUserSummary.php
    SettlementEnvelopeContextBuilder.php
    SettlementEnvelopePersister.php
    SettlementPhaseSummary.php
    SettlementScenarioSupport.php
    WalletTransactionSnapshot.php

src/Lifecycle/Output
    LifecycleOutputContract.php
    ConsoleLifecycleOutput.php
    NullLifecycleOutput.php
    BufferedLifecycleOutput.php

src/Console/Commands/Lifecycle
    RunLifecycleScenarioCommand.php
    PrepareLifecycleEnvironmentCommand.php

src/Http/Controllers/Lifecycle
    RunLifecycleScenarioController.php
```

---

# 2. Current State

The system already has the important architectural pieces:

- `RunLifecycleScenarioCommand`
- `RunLifecycleScenarioController`
- `LifecycleScenarioEngine`
- `ScenarioRunnerResolver`
- `ScenarioRunnerRegistry`
- `DefaultClaimScenarioRunner`
- `SequentialClaimsScenarioRunner`
- `SettlementEnvelopeEvaluationScenarioRunner`
- `SettlementThreePartyScenarioRunner`
- `LifecycleOutputContract`
- `ConsoleLifecycleOutput`
- `NullLifecycleOutput`
- `LifecycleDisbursementPoller`
- lifecycle API route:
    - `POST /api/x/v1/lifecycle/scenarios/run`

The next work is mostly **rationalization**, **renaming/moving**, **scenario governance**, and **operational documentation**.

---

# 3. Design Boundary

The Lifecycle Scenario Runtime is not the voucher package, wallet package, EMI package, or settlement package.

It is the **operational runtime** that proves those packages work together.

```text
Packages provide capabilities.
Lifecycle Scenario Runtime proves capabilities work together.
```

Examples:

```text
voucher changed
→ run issuance, claim, collectible, settlement scenarios

wallet changed
→ run debit, credit, top-up, settlement scenarios

emi-core changed
→ run withdrawal, disbursement, reconciliation scenarios

contact changed
→ run KYC/contact-bound redemption scenarios

settlement-envelope changed
→ run blocked/ready/three-party settlement scenarios
```

---

# 4. Migration Plan to Target Shape

## Slice 1 — Move Scenario Core

Move:

```text
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleScenarioEngine.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleScenarioRunOptions.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleScenarioEngineResult.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleScenarioRepository.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleScenarioBootstrapper.php
src/Console/Commands/Lifecycle/ScenarioRunners/Support/LifecycleScenarioBootstrapResult.php
```

To:

```text
src/Lifecycle/Scenarios/
```

Update namespaces:

```php
namespace LBHurtado\XChange\Lifecycle\Scenarios;
```

Update all imports in:

- command
- controller
- tests
- runners
- support services

Run focused tests:

```bash
./vendor/bin/pest tests/Feature/Console/LifecycleScenarioCommandTest.php
./vendor/bin/pest tests/Feature/Api/Lifecycle/RunLifecycleScenarioRouteTest.php
./vendor/bin/pest tests/Feature/Console/LifecycleScenarioEngineTest.php
```

---

## Slice 2 — Move Runner Core

Move:

```text
ScenarioRunnerContract.php
ScenarioRunContext.php
ScenarioRunResult.php
ScenarioRunnerResolver.php
ScenarioRunnerRegistry.php
ScenarioRunnerResolution.php
DefaultClaimScenarioRunner.php
SequentialClaimsScenarioRunner.php
SettlementEnvelopeEvaluationScenarioRunner.php
SettlementThreePartyScenarioRunner.php
```

From:

```text
src/Console/Commands/Lifecycle/ScenarioRunners/
```

To:

```text
src/Lifecycle/Runners/
```

Update namespace:

```php
namespace LBHurtado\XChange\Lifecycle\Runners;
```

Run:

```bash
./vendor/bin/pest tests/Feature/Console/LifecycleScenarioCommandTest.php
./vendor/bin/pest tests/Feature/Console/LifecycleSequentialClaimsScenarioRunnerTest.php
./vendor/bin/pest tests/Feature/Console/LifecycleSettlementEnvelopeScenarioTest.php
./vendor/bin/pest tests/Feature/Console/LifecycleSettlementThreePartyScenarioTest.php
```

---

## Slice 3 — Move Runner Support

Move:

```text
LifecycleClaimAttemptEvaluator.php
LifecycleClaimResultNormalizer.php
LifecycleDisbursementPoller.php
LifecycleUserSummary.php
SettlementEnvelopeContextBuilder.php
SettlementEnvelopePersister.php
SettlementPhaseSummary.php
SettlementScenarioSupport.php
WalletTransactionSnapshot.php
```

To:

```text
src/Lifecycle/Runners/Support/
```

Namespace:

```php
namespace LBHurtado\XChange\Lifecycle\Runners\Support;
```

Run all lifecycle runner tests.

---

## Slice 4 — Move Output Layer

Move:

```text
LifecycleOutputContract.php
ConsoleLifecycleOutput.php
NullLifecycleOutput.php
```

To:

```text
src/Lifecycle/Output/
```

Add:

```text
BufferedLifecycleOutput.php
```

Namespace:

```php
namespace LBHurtado\XChange\Lifecycle\Output;
```

`BufferedLifecycleOutput` should collect messages for API/demo use:

```php
final class BufferedLifecycleOutput implements LifecycleOutputContract
{
    private array $messages = [];

    public function line(string $message): void { $this->messages[] = ['level' => 'line', 'message' => $message]; }
    public function info(string $message): void { $this->messages[] = ['level' => 'info', 'message' => $message]; }
    public function warn(string $message): void { $this->messages[] = ['level' => 'warn', 'message' => $message]; }
    public function error(string $message): void { $this->messages[] = ['level' => 'error', 'message' => $message]; }

    public function isJson(): bool { return true; }
    public function acceptPending(): bool { return true; }

    public function messages(): array { return $this->messages; }
}
```

Update tests.

---

## Slice 5 — Keep Adapters Thin

Ensure these remain adapters only:

```text
RunLifecycleScenarioCommand
RunLifecycleScenarioController
```

They should only:

1. accept input
2. build `LifecycleScenarioRunOptions`
3. create appropriate output adapter
4. call `LifecycleScenarioEngine`
5. render/return result

They must not contain:

- scenario execution logic
- runner selection logic
- polling logic
- settlement envelope logic
- claim payload evaluation logic
- wallet transaction snapshot logic

---

# 5. Scenario Categories

Create scenario categories to support operational use.

## A. Smoke Scenarios

Purpose:

Quick confidence checks.

Examples:

```text
basic_cash_no_claim
secret_required_success
wallet_debit_smoke
collectible_no_claim
settlement_envelope_ready_smoke
```

Usage:

```bash
php artisan xchange:lifecycle:run basic_cash --timeout=1 --poll=1
```

Use for:

- local dev confidence
- CI smoke tests
- deployment readiness

---

## B. Contract Scenarios

Purpose:

Prove business rules.

Examples:

```text
secret_required
mobile_locked_contract
otp_required_contract
location_radius_contract
bio_inputs_contract
signature_required
selfie_required
kyc_required_unapproved
kyc_required_approved
```

Use for:

- regression testing
- rules validation
- acceptance testing

---

## C. Provider Scenarios

Purpose:

Prove integrations with EMI/payment/QR providers.

Examples:

```text
provider_disbursement_pending
provider_disbursement_succeeded
provider_disbursement_failed
provider_payment_qr_generated
provider_payment_webhook_confirmed
```

Use for:

- provider adapter testing
- EMI sandbox checks
- deployment verification

---

## D. Settlement Scenarios

Purpose:

Prove settlement envelope and multi-party settlement flows.

Examples:

```text
settlement_philhealth_bst_blocked
settlement_philhealth_bst_ready
settlement_philhealth_bst_three_party
settlement_collectible_gate
settlement_attestation_required
```

Use for:

- PhilHealth BST
- settlement readiness
- multi-party validation
- payer/attestor/recipient workflows

---

## E. Reconciliation Scenarios

Purpose:

Prove recovery and reconciliation behavior.

Examples:

```text
reconciliation_provider_failure
reconciliation_pending_review
reconciliation_resolved_success
reconciliation_duplicate_provider_reference
reconciliation_missing_provider_reference
```

Use for:

- incident handling
- provider mismatch
- failed disbursement recovery
- audit readiness

---

## F. Partner Onboarding Scenarios

Purpose:

Prove partner setup and readiness.

Examples:

```text
partner_issuer_onboarding
partner_wallet_opening
partner_first_paycode_issuance
partner_claim_endpoint_ready
partner_settlement_config_ready
```

Use for:

- bank onboarding
- EMI onboarding
- sandbox certification
- implementation readiness

---

## G. Regression Scenarios

Purpose:

Longer suite of critical workflows.

Examples:

```text
regression_all_claim_contracts
regression_all_settlement_flows
regression_all_reconciliation_flows
regression_partner_certification
```

Use for:

- nightly CI
- release candidate validation
- package compatibility testing

---

# 6. Operational Documents to Create

Create the following documents.

---

## Document 1 — `docs/lifecycle-scenario-runtime.md`

Purpose:

Explain the subsystem.

Contents:

```text
# Lifecycle Scenario Runtime

## Purpose
## Architecture
## CLI Adapter
## HTTP Adapter
## Scenario Engine
## Runners
## Output Adapters
## Scenario Categories
## Operational Use Cases
## How to Add a Scenario
## How to Add a Runner
## How to Run Tests
```

Key phrase:

> Lifecycle Scenario Runtime is the operational execution layer for proving that x-change package capabilities work together end-to-end.

---

## Document 2 — `docs/operations/pre-deployment-checks.md`

Purpose:

Checklist before deploy.

Contents:

```text
# Pre-Deployment Checks

## Goal
Prevent broken money movement flows from reaching production.

## Required Checks
- smoke scenarios
- claim contract scenarios
- settlement envelope scenarios
- reconciliation scenarios
- provider adapter scenarios when provider code changed

## Example Commands
php artisan xchange:lifecycle:run basic_cash --timeout=1 --poll=1
php artisan xchange:lifecycle:run secret_required --timeout=1 --poll=1
php artisan xchange:lifecycle:run settlement_philhealth_bst_three_party --json
php artisan xchange:lifecycle:run reconciliation_provider_failure --json

## Pass Criteria
- all expected failures fail as expected
- all expected successes succeed
- no unexpected reconciliation entries
- wallet balances remain consistent
- settlement readiness matches expected state
```

---

## Document 3 — `docs/operations/post-deployment-checks.md`

Purpose:

Verify after deploy.

Contents:

```text
# Post-Deployment Checks

## Goal
Confirm deployed environment can execute key lifecycle flows.

## Recommended Checks
- no-claim issuance smoke test
- collectible payment smoke test
- settlement readiness check
- disbursement status check
- reconciliation status check

## API Example
POST /api/x/v1/lifecycle/scenarios/run

{
  "scenario": "basic_cash",
  "no_claim": true,
  "timeout": 1,
  "poll": 1
}

## Expected Result
200 response with generated voucher code and valid attempt summary.
```

---

## Document 4 — `docs/operations/partner-certification.md`

Purpose:

Use lifecycle scenarios to certify partners.

Contents:

```text
# Partner Certification

## Goal
Certify that a bank, EMI, or partner integration can support x-change flows.

## Certification Groups
- issuance
- redemption
- withdrawal/disbursement
- payment collection
- settlement
- reconciliation
- webhook handling

## Certification Process
1. provision partner sandbox
2. configure issuer/wallet/provider
3. run smoke scenarios
4. run contract scenarios
5. run provider scenarios
6. run settlement scenarios if applicable
7. export results
8. sign off partner readiness

## Output
Partner Certification Report
```

---

## Document 5 — `docs/operations/incident-reproduction.md`

Purpose:

Reproduce production incidents safely.

Contents:

```text
# Incident Reproduction

## Goal
Turn incidents into repeatable lifecycle scenarios.

## Process
1. identify failed flow
2. capture voucher metadata
3. identify involved packages
4. create scenario
5. reproduce failure in sandbox
6. fix
7. keep scenario as regression test

## Example
Provider returned failed but wallet debit succeeded.
Create reconciliation_provider_failure scenario.
```

---

## Document 6 — `docs/operations/provider-regression-testing.md`

Purpose:

Test EMI/payment provider adapters.

Contents:

```text
# Provider Regression Testing

## Goal
Ensure provider integrations remain stable.

## Provider Scenario Types
- happy path
- pending path
- failure path
- timeout path
- duplicate callback
- mismatched amount
- missing provider reference

## Required Before Provider Release
- provider_disbursement_succeeded
- provider_disbursement_failed
- reconciliation_pending_review
- reconciliation_resolved_success
```

---

## Document 7 — `docs/operations/demo-automation.md`

Purpose:

Use scenarios for demos.

Contents:

```text
# Demo Automation

## Goal
Generate predictable demo flows.

## Demo Types
- Pay Code redemption demo
- collectible voucher demo
- settlement envelope demo
- three-party settlement demo
- reconciliation recovery demo

## CLI Usage
php artisan xchange:lifecycle:run settlement_philhealth_bst_three_party --json

## API Usage
POST /api/x/v1/lifecycle/scenarios/run
```

---

## Document 8 — `docs/operations/bank-sandbox-validation.md`

Purpose:

Sandbox readiness for banks.

Contents:

```text
# Bank Sandbox Validation

## Goal
Validate that a bank-hosted x-change sandbox is ready.

## Validation Areas
- issuer creation
- wallet funding
- voucher issuance
- claim endpoint
- disbursement provider
- settlement readiness
- reconciliation visibility
- webhook handling

## Exit Criteria
- all smoke scenarios pass
- all contract scenarios pass
- provider scenarios pass
- settlement scenarios pass if enabled
```

---

# 7. Scenario Configuration Governance

Add a convention to scenario config.

Each scenario should have:

```php
[
    'label' => 'Human readable label',
    'category' => 'smoke|contract|provider|settlement|reconciliation|partner|regression',
    'mode' => 'default|sequential_claims|settlement_envelope_evaluation|settlement_three_party_flow',
    'tags' => ['wallet', 'voucher', 'emi-core'],
    'risk' => 'low|medium|high',
    'description' => 'What this scenario proves.',
]
```

Example:

```php
'secret_required' => [
    'label' => 'Secret Required Redemption',
    'category' => 'contract',
    'mode' => 'default',
    'tags' => ['voucher', 'redemption', 'validation'],
    'risk' => 'medium',
    'description' => 'Proves a voucher requiring a secret cannot be redeemed without the correct secret.',
]
```

---

# 8. Scenario Execution Reports

Add a report shape later:

```php
[
    'scenario' => 'secret_required',
    'category' => 'contract',
    'status' => 'passed',
    'started_at' => '...',
    'finished_at' => '...',
    'duration_ms' => 1234,
    'runner' => 'DefaultClaimScenarioRunner',
    'attempt_summary' => [
        'passed' => 2,
        'failed' => 0,
        'total' => 2,
    ],
    'artifacts' => [
        'voucher_code' => 'ABCD',
        'wallet_transactions' => [],
        'reconciliation' => null,
    ],
]
```

This can later feed:

- dashboards
- CI artifacts
- partner certification reports
- incident reports

---

# 9. API Roadmap

Current endpoint:

```text
POST /api/x/v1/lifecycle/scenarios/run
```

Add later:

```text
GET  /api/x/v1/lifecycle/scenarios
GET  /api/x/v1/lifecycle/scenarios/{scenario}
POST /api/x/v1/lifecycle/scenarios/run
POST /api/x/v1/lifecycle/scenario-groups/{group}/run
GET  /api/x/v1/lifecycle/runs/{run_id}
```

Future concerns:

- auth middleware
- environment guard
- disable in production by default
- audit log
- async queue execution
- downloadable report
- partner-scoped sandbox scenarios

---

# 10. Operational Use Cases

## A. Pre-Deployment Checks

Run before every release.

```bash
php artisan xchange:lifecycle:run basic_cash --timeout=1 --poll=1
php artisan xchange:lifecycle:run secret_required --timeout=1 --poll=1
php artisan xchange:lifecycle:run settlement_philhealth_bst_three_party --json
```

Goal:

Prevent broken lifecycle behavior from reaching production.

---

## B. Post-Deployment Checks

Run immediately after deployment.

```http
POST /api/x/v1/lifecycle/scenarios/run
{
  "scenario": "basic_cash",
  "no_claim": true,
  "timeout": 1,
  "poll": 1
}
```

Goal:

Confirm deployed environment can issue and evaluate scenarios.

---

## C. Partner Certification

Run against a bank/EMI sandbox.

Required groups:

```text
smoke
contract
provider
settlement
reconciliation
```

Output:

```text
Partner Certification Report
```

Goal:

Certify partner integration readiness.

---

## D. Incident Reproduction

Turn production failure into scenario.

Example:

```text
Incident:
Provider marked transaction failed after wallet debit.

Scenario:
reconciliation_provider_failure
```

Goal:

Every incident becomes a repeatable regression scenario.

---

## E. Provider Regression Testing

Run before changing EMI/payment provider code.

Examples:

```text
provider_disbursement_succeeded
provider_disbursement_failed
provider_timeout_pending
provider_duplicate_callback
```

Goal:

Prevent provider adapter regressions.

---

## F. Demo Automation

Use lifecycle scenarios to generate demo data and predictable flows.

Examples:

```text
settlement_philhealth_bst_three_party
collectible_basic_payment
secret_required
```

Goal:

Repeatable roadshow and partner demos.

---

## G. Bank Sandbox Validation

Run after installing x-change in a bank sandbox.

Checks:

```text
issuer
wallet
voucher
claim
payment
settlement
reconciliation
provider
```

Goal:

Prove the sandbox is operational.

---

# 11. Near-Term Implementation Order

Recommended next thread sequence:

## Thread 1 — Namespace Rationalization

Move files to target shape.

Goal:

```text
Console stops owning runtime namespaces.
Lifecycle subsystem becomes first-class.
```

---

## Thread 2 — Scenario Metadata Governance

Add:

```text
category
tags
risk
description
```

to scenario config.

Update repository to filter/list by category.

---

## Thread 3 — Scenario Group Runner

Add:

```text
run category smoke
run category contract
run group pre-deployment
```

CLI examples:

```bash
php artisan xchange:lifecycle:run-group smoke
php artisan xchange:lifecycle:run-group pre-deployment
```

API example:

```http
POST /api/x/v1/lifecycle/scenario-groups/pre-deployment/run
```

---

## Thread 4 — Operational Documentation

Create docs listed above.

---

## Thread 5 — Report Persistence

Add optional lifecycle run records.

Possible model:

```text
LifecycleScenarioRun
LifecycleScenarioRunAttempt
```

Store:

- scenario
- category
- payload
- result
- duration
- environment
- status

---

# 12. Definition of Done

The Lifecycle Scenario Runtime is complete when:

- core classes live under `src/Lifecycle`
- command and controller are thin adapters
- scenarios are categorized
- scenario groups can run
- docs exist for operational workflows
- API can run individual scenarios
- CI can run smoke/contract/regression groups
- partner certification can generate report artifacts

---

# 13. Guiding Principle

The runtime should answer one question:

> Can x-change perform this business lifecycle correctly, right now, in this environment?

If the answer can be executed and verified automatically, the system becomes robust.
