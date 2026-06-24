# Execution Engine: Target State

Status: Canonical architectural direction  
Last reviewed: 2026-06-24

## Thesis

A Pay Code is an external portable contract handle. The voucher is the internal executable instruction contract. Redemption activates execution; it does not necessarily imply immediate money movement.

```text
Voucher
    -> Execution Instruction
    -> Execution Engine
    -> Execution Driver
    -> Execution Pipeline
    -> Structured Execution Outcome
```

## Ownership

Voucher will own execution semantics and the future runtime:

- `ExecutionInstructionData`
- `ExecutionEngine`
- `ExecutionDriverContract`
- `ExecutionDriverRegistry`
- `ExecutionContextData`
- `ExecutionResultData`
- driver resolution and execution orchestration

x-change remains the settlement operating-system/product layer responsible for claim experience, compiler and UI, APIs, lifecycle scenarios, provider integrations, settlement integrations, analytics, and reporting. It consumes voucher behavior through contracts.

Provider-specific payout implementations remain outside the voucher engine. A voucher driver may invoke payout behavior through stable contracts.

Settlement Envelope remains a participant, readiness gate, authorization structure, and audit/evidence owner. It is not the execution engine.

## Runtime Boundary

```text
Claim UX / API
    -> collected evidence and submit contract
    -> voucher redemption contract
    -> voucher-owned execution engine
    -> registry-resolved driver
    -> existing or driver-specific pipeline
    -> structured result and events
    -> x-change journal/reconciliation/feedback consumers
```

## Compatibility

Legacy vouchers without an explicit execution block must retain current behavior. Their effective instruction is conceptually:

```yaml
execution:
  driver: default
```

The eventual default driver delegates to the behavior characterized in Slice 0, including the existing `voucher-pipeline.php` post-redemption sequence.

## Driver Model

Drivers are resolved through a registry, are extensible by package consumers, and return a structured result. Conditional driver selection must not spread through x-change or claim UI code.

The planned sequence is:

1. Default compatibility driver.
2. Settlement-envelope driver after the default architecture stabilizes.
3. Stored-value driver after earlier slices remain green.
4. Optional driver-composed pipelines only after proven need.

Stored value is behavior selected by instruction, not a new voucher subclass. The same principle prevents voucher-type proliferation.

## Data Concepts

Future DTOs need at least these concepts:

- Instruction: driver key, optional mode/pipeline/fallback, visibility, metadata.
- Context: voucher, claimant/contact, collected inputs, correlation metadata.
- Result: success/status, events, failure detail, provider/reconciliation references, child results, metadata.

The exact PHP shapes in planning documents are illustrative proposals. They are not frozen public APIs and must be introduced test-first in their authorized slices.

## Visibility and Audit

Every path that reserves or moves value must expose who, voucher, driver, amount, recipient, provider reference, envelope/child relationships, status, and failure reason. Visibility requirements may be declared, but lifecycle truth remains with the domain and execution records—not feedback or UI.

## Explicit Separations

- Claim UX prepares evidence; it does not decide execution consequences.
- x-change orchestrates product workflows; it does not own voucher execution drivers.
- Settlement Envelope determines readiness and authorization; it does not execute itself.
- Providers implement payout details; the engine invokes contracts.
- `voucher-pipeline.php` remains the compatibility lifecycle pipeline until a later approved migration says otherwise.

## Non-goals

This target does not require a claim UI rewrite, provider rewrite, settlement-envelope rewrite, voucher subclass explosion, or immediate removal of current x-change orchestration contracts.
