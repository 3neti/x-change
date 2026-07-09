# Cockpit Mutation Wave 2 — Operator-visible Issuance Activity and Audit Handoff Plan

Status: Plan drafted; no implementation authorized in this slice

## Purpose

Define the next mutation wave after Quick Generate issuance became operator-usable.

Wave 1 lets an authorized operator submit Quick Generate requests through the existing x-change issuance handoff. Wave 2 should make that operator activity visible and ready for future audit/journal/action handoff without making Cockpit the owner of lifecycle truth.

This is a planning slice only.

## Current Boundary

Current Quick Generate mutation path:

```text
Cockpit Quick Generate UI
    ↓
POST /x/cockpit/quick-generate
    ↓
GeneratePayCodeRequest
    ↓
GeneratePayCode
    ↓
existing x-change issuance behavior
    ↓
operator-safe Cockpit response
```

Current post-submit UI behavior:

```text
operator-safe result
    ↓
manual read-model refresh
    ↓
Cockpit Voucher Detail navigation
```

## Wave 2 Objective

Provide an operator-visible issuance activity layer that records and presents safe Cockpit mutation facts while preserving the existing owners:

| Concern | Owner |
| --- | --- |
| issuance behavior | x-change `GeneratePayCode` path |
| voucher execution semantics | voucher package |
| system memory / audit truth | future x-journal integration |
| workflow continuation | future x-action handoff |
| notification delivery | future x-feedback handoff |
| operator presentation | x-change Cockpit |

## Proposed Slice Sequence

### Wave 2A — Operator Issuance Activity Read Model Contract

Deliverables:

- define a Cockpit read-model contract for recent operator issuance activity;
- include only operator-safe facts such as code, amount, currency, status, issued_at, route, and correlation references;
- include unavailable/not-wired states;
- add presentation tests;
- no persistence yet.

### Wave 2B — Issuance Activity Recorder Boundary

Deliverables:

- define a package-local boundary for recording operator issuance activity;
- allow a null/no-op recorder by default;
- prove the recorder receives only redacted response facts and correlation metadata;
- no database tables, queues, or journal dependency yet.

### Wave 2C — Journal Handoff Boundary

Deliverables:

- define the adapter shape for future x-journal handoff;
- keep x-journal optional and adapter-driven;
- prove missing x-journal degrades safely;
- do not write journal entries until explicitly authorized.

### Wave 2D — Action Handoff Boundary

Deliverables:

- define future x-action hints produced after issuance, such as “open detail,” “share,” or “review funding”;
- keep x-action optional and adapter-driven;
- do not execute actions from Cockpit.

### Wave 2E — Feedback Handoff Boundary

Deliverables:

- define future x-feedback delivery-intent handoff shape for optional recipient/operator communication;
- keep delivery non-executing unless explicitly authorized;
- no provider delivery, retry, suppression, or durable delivery records.

### Wave 2F — Activity Presentation Closure

Deliverables:

- render operator-visible activity in Quick Generate and/or Dashboard;
- include unavailable and empty states;
- update Compass and closure report;
- keep activity display read-only.

## Required Gates Before Runtime Work

| Gate | Required decision |
| --- | --- |
| Activity scope | Decide whether activity is package-local, journal-backed, or both. |
| Persistence | Decide if activity records require durable storage before journal integration. |
| Correlation | Define how idempotency key, correlation ID, operator ID, and generated code are linked. |
| Redaction | Confirm no raw request payloads, wallet/debit internals, provider payloads, recipient secrets, OTPs, or unapproved internal IDs are exposed. |
| Optional packages | Keep x-journal, x-action, and x-feedback adapter-driven and optional. |
| Lifecycle truth | Confirm Cockpit activity is presentation/operation evidence, not lifecycle truth. |

## Explicit Non-Implementation Statement

This planning slice does not add:

- database tables;
- migrations;
- queues;
- journal writes;
- x-journal hard dependency;
- action execution;
- x-action hard dependency;
- feedback delivery;
- x-feedback hard dependency;
- campaign mutation;
- provider calls;
- wallet lookup, reservation, debit, or transfer;
- voucher execution changes;
- raw payload persistence;
- automatic retry;
- money movement.

## Recommended Next Slice

```text
Cockpit Mutation Wave 2A — Operator Issuance Activity Read Model Contract
```

Wave 2A may add DTO/read-model contract scaffolding, safe default unavailable states, presentation components, reports, and tests. It must not add persistence, journal writes, action execution, feedback delivery, provider calls, wallet access, or money movement.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitMutationWave2OperatorActivityPlanTest.php
```

Result: `1 passed, 16 assertions`.

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitMutationWave2OperatorActivityPlanTest.php tests/Unit/Architecture/CockpitQuickGenerateRefreshNavigationClosureTest.php
```

Result: `2 passed, 29 assertions`.

```bash
composer validate --strict
```

Result: `./composer.json is valid`.

```bash
php -d memory_limit=1G vendor/bin/pest
```

Result: `1086 passed, 5 skipped, 6455 assertions`.
