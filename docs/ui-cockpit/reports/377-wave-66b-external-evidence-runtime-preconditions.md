# Cockpit Wave 66B — Manual Distribution External Evidence Runtime Preconditions

## Status

Scaffolded / Runtime preconditions recorded.

## Purpose

Wave 66B defines the preconditions required before external evidence intake can become runtime behavior.

## Required Preconditions

Runtime evidence intake must not begin until all of these are explicitly approved:

- Authorization gate for creating evidence.
- Authorization gate for viewing evidence.
- Tenant and operator scoping strategy.
- Redaction policy for submitted references and notes.
- Validation policy for allowed evidence fields.
- Retention and purge policy.
- Rejection and correction workflow.
- Journal handoff decision.
- x-feedback correlation decision.
- x-action continuation decision.
- x-campaign attribution decision.
- Attachment policy.
- Browser/UI presentation policy.
- Abuse and accidental disclosure runbook.

## Required Runtime Shape Before Implementation

Before implementation, define:

- A final request contract.
- A final response contract.
- A storage decision.
- A read-model contract.
- An authorization policy.
- A redaction policy.
- A test matrix.
- A rollback plan.

## Runtime Gate Decision

`runtime-blocked / preconditions-required`

## Explicit Stop Condition

Do not add evidence forms, routes, controllers, migrations, models, DTOs, storage, journal handoff, feedback correlation, action completion, campaign attribution, provider calls, voucher mutation, wallet mutation, or money movement until the preconditions are approved.

## Next Checkpoint

Cockpit Wave 66C — Manual Distribution External Evidence Runtime Decision Closure.
