# Cockpit Mutation Wave 3A — Durable Activity Storage Boundary Plan

Status: Implemented as planning-only boundary
Date: 2026-07-10

## Purpose

Define the boundary for future durable operator issuance activity storage before adding any database schema, model, repository, queue, worker, or UI mutation behavior.

This plan follows the accepted Wave 2L visual state:

```text
Operator Issuance Activity
Quick Generate evidence
presentation-only
No operator issuance activity available
Activity recording is not wired yet. Quick Generate can still use the existing issuance path.
```

## Current State

The Cockpit dashboard already has:

- an operator-visible activity panel
- a presentation-only activity read model
- a recorder contract boundary
- null/no-op journal, action, and feedback handoff boundaries
- human-confirmed read-only UI behavior

The dashboard currently does not have:

- durable activity storage
- activity database tables
- activity Eloquent models
- activity repositories
- queue-backed activity recording
- activity retention policy enforcement
- activity replay/reconciliation
- journal/action/feedback write execution

## Target Durable Storage Boundary

Future durable activity storage should record operator-safe issuance activity facts emitted by existing x-change issuance flows.

The durable storage layer should own only:

- operator issuance activity records
- safe activity status snapshots
- redacted operator-facing summaries
- correlation identifiers
- idempotency references
- handoff status facts
- retention metadata
- read-model retrieval for Cockpit

It must not own:

- voucher execution semantics
- voucher lifecycle truth
- journal truth
- action execution
- feedback delivery
- provider callbacks
- wallet mutation
- money movement
- campaign mutation
- claim UX behavior

## Proposed Record Shape

This is a planning shape only. Do not implement it in this slice.

```text
operator_issuance_activities
  id
  activity_id
  actor_id
  actor_label
  source
  subject_type
  subject_reference
  status
  severity
  occurred_at
  idempotency_key_hash
  correlation_id
  causation_id
  summary
  safe_context
  redaction_flags
  journal_handoff_status
  action_handoff_status
  feedback_handoff_status
  retention_until
  created_at
  updated_at
```

## Redaction Policy

Durable activity records should store operator-safe data only.

Allowed:

- voucher code or masked voucher code, depending on existing Cockpit redaction policy
- activity status
- safe label
- public route references
- high-level handoff status
- correlation IDs
- idempotency hash
- actor label or actor ID when authorized

Not allowed:

- raw request payloads
- raw provider payloads
- secrets
- OTPs
- unmasked financial account identifiers
- full beneficiary PII unless explicitly authorized by a future redaction policy
- feedback message bodies unless explicitly classified safe
- journal payload bodies
- action execution payloads

## Retention Policy

Future storage must define:

- default retention window
- purge eligibility
- legal/audit hold override behavior
- whether deleted records leave tombstones
- whether summary counts survive purge

Until that policy is approved, no durable storage should be added.

## Correlation Policy

Each durable activity record should support correlation with:

- Cockpit request ID
- issuance idempotency key hash
- generated Pay Code or voucher code
- execution ID when available
- future journal entry ID when handoff is wired
- future action run ID when handoff is wired
- future feedback delivery ID when handoff is wired

Correlation should be append-friendly. Storage must not require journal/action/feedback packages to be installed.

## Read Model Policy

Cockpit should consume durable activity through a read-model adapter.

The adapter should:

- return redacted presentation data only
- support empty state
- support unavailable state
- support pagination or capped recent activity
- avoid exposing raw storage rows directly to Vue props
- keep the current `presentation-only` UI semantics until mutation controls are explicitly approved

## Future Slice Sequence

Recommended implementation order:

1. Wave 3B — Durable Activity DTO and Repository Contract
2. Wave 3C — In-Memory Durable Activity Repository Baseline
3. Wave 3D — Activity Redaction and Retention Policy Contracts
4. Wave 3E — Database Migration Decision Point
5. Wave 3F — Durable Activity Migration and Model Baseline
6. Wave 3G — Repository Persistence Adapter
7. Wave 3H — Quick Generate Recorder Persistence Wiring
8. Wave 3I — Dashboard Read Model Hydration from Durable Activity
9. Wave 3J — Journal/Action/Feedback Handoff Status Persistence
10. Wave 3K — Manual Browser Verification and Closure

## Explicit Non-Authorization

This slice does not authorize:

- migrations
- Eloquent models
- database writes
- repositories
- queue jobs
- scheduled purges
- journal writes
- action execution
- feedback delivery
- provider calls
- wallet access
- voucher execution changes
- lifecycle truth ownership
- raw payload persistence
- UI changes
- mutation controls
- money movement

## UI Impact

No UI was changed in this slice.

The current visible dashboard state remains:

```text
Operator Issuance Activity
Quick Generate evidence
presentation-only
No operator issuance activity available
Activity recording is not wired yet.
```

## Decision

Durable operator issuance activity storage is desirable, but implementation must start with contracts and redaction/retention policy before persistence.

The next slice should remain non-database:

```text
Cockpit Mutation Wave 3B — Durable Activity DTO and Repository Contract
```
