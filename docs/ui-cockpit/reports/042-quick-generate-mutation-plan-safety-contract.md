# Cockpit Mutation Wave 1 — Quick Generate Draft-to-Issuance Boundary Plan

Status: Plan drafted; no implementation authorized in this slice

## Purpose

Define the first mutation-capable Cockpit implementation plan without implementing it.

This plan follows the read-only Cockpit visual validation pass. It translates the existing Quick Generate read-only readiness work into a future mutation sequence while keeping the current codebase unchanged.

## Current Source Boundary

The existing issuance owner is x-change, not Cockpit UI code.

Current issuance path:

```text
GeneratePayCodeRequest
    ↓
GeneratePayCodeController
    ↓
GeneratePayCode
    ↓
wallet / pricing / revenue allocation / voucher issuance behavior already owned by x-change
```

Current API route:

```text
POST /api/x-change/pay-codes
```

Current Cockpit route:

```text
GET /x/cockpit/quick-generate
```

The future Cockpit mutation must hand off to the existing issuance path. It must not duplicate voucher generation logic in Vue components, Inertia page controllers, or Cockpit-specific services.

## Implementation Rule

Use this pattern:

```text
Action → Service → API → UI
```

Avoid this pattern:

```text
UI → Controller → Logic
```

Cockpit may collect and submit an operator issuance request. Cockpit must not become the source of issuance, pricing, funding, execution, journal, action, feedback, wallet, or provider truth.

## First Mutation-Capable Slice

Recommended first implementation slice:

```text
Cockpit Mutation Wave 1A — Quick Generate Mutation Contract and Safety Gates
```

Scope:

- introduce a Cockpit-specific request contract or adapter that maps to the existing `GeneratePayCodeRequest` / `GeneratePayCode` path
- define the route name and payload envelope before enabling the UI submit button
- preserve existing issuance behavior
- prove Cockpit does not bypass the existing action/controller boundary
- keep generated response payload redacted for operators
- keep idempotency explicit
- keep mutation authorization explicit

Non-goals:

- no voucher generation behavior changes
- no voucher execution changes
- no provider behavior changes
- no wallet debit/reservation redesign
- no journal writes beyond behavior already performed by the existing issuance path
- no action execution
- no feedback delivery
- no campaign issuance behavior
- no raw payload exposure
- no money movement behavior changes

## Required Gates Before Enabling Submit

The following gates must be green before a visible Quick Generate submit mutation is enabled:

| Gate | Required decision |
| --- | --- |
| Authorization | The operator can issue Pay Codes through Cockpit. |
| Validation | Payload is validated by existing x-change request/action rules or a strict adapter to those rules. |
| Pricing | Pricing result is produced by the existing x-change pricing/cost path. |
| Funding | Existing funding/wallet behavior is reused; no Cockpit wallet mutation logic is introduced. |
| Idempotency | Operator request has an idempotency key and deterministic replay/conflict policy. |
| Redaction | Operator response excludes provider payloads, wallets, raw voucher payloads, secrets, OTPs, recipient addresses, and internal IDs unless explicitly approved. |
| Side-effect ownership | All side effects occur inside existing x-change issuance boundaries. |
| Error contract | Validation, authorization, funding, and issuance failures have operator-safe errors. |
| Observability | Journal/action/feedback handoff remains boundary-only unless explicit integration exists. |

## Proposed Slice Sequence

### Wave 1A — Mutation Contract and Safety Gates

Deliverables:

- route and request contract plan
- idempotency key strategy
- authorization rule
- operator-safe response contract
- architecture tests proving no bypass of `GeneratePayCode`
- no route implementation yet unless explicitly approved after the contract is reviewed

### Wave 1B — Mutation Route Shell

Deliverables:

- Cockpit `POST` route shell
- authorization check
- validation adapter
- mocked/faked issuance handoff in tests
- no UI button enablement yet

### Wave 1C — Existing Issuance Handoff

Deliverables:

- route shell calls existing `GeneratePayCode` action
- request maps through existing issuance-compatible payload
- response redacts result for operator presentation
- tests prove existing API behavior is not changed

### Wave 1D — Idempotency and Replay Contract

Deliverables:

- idempotency key acceptance
- replay-safe result behavior
- conflict behavior
- no duplicate issuance on repeated operator submit

### Wave 1E — UI Submit Enablement

Deliverables:

- Quick Generate form submit uses the approved route
- disabled state becomes conditional on the safety gates
- optimistic UI is not used for issuance
- operator result shows safe generated facts only

### Wave 1F — Read-Model Refresh and Closure

Deliverables:

- post-issuance redirect or refresh behavior
- Pay Code Explorer / Voucher Detail navigation handoff
- updated Compass and closure report
- focused feature/frontend tests green

## Test Strategy

Before production changes:

- add architecture tests proving Cockpit has no mutation route until Wave 1B is explicitly authorized
- add contract tests for request payload shape
- add feature tests around authorization and validation failures
- add feature tests that fake `GeneratePayCode`
- add regression tests proving existing `/api/x-change/pay-codes` behavior is unchanged
- add frontend tests proving the submit button remains disabled until the route and gates exist

After production changes begin:

- run focused Cockpit route tests
- run GeneratePayCode unit/feature tests
- run relevant frontend tests
- run package Pest suite if mutation behavior touches issuance paths

## Explicit Non-Implementation Statement

This planning slice does not add:

- Cockpit `POST`, `PUT`, `PATCH`, or `DELETE` routes
- request validation execution
- payload persistence
- idempotency persistence
- `GeneratePayCode` invocation
- `GeneratePayCodeController` invocation
- voucher issuance
- voucher execution
- provider calls
- wallet lookup, reservation, debit, or transfer
- journal writes
- action execution
- feedback delivery
- campaign issuance behavior
- money movement

## Authorization State

This document authorizes planning only.

Implementation remains blocked until a future instruction explicitly authorizes:

```text
Cockpit Mutation Wave 1A — Quick Generate Mutation Contract and Safety Gates
```

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitQuickGenerateMutationPlanTest.php
```
