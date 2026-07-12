# Cockpit Wave 65A — Manual Distribution External Evidence Intake Decision

## Status

Scaffolded / Decision recorded.

## Purpose

Wave 65A decides whether Cockpit should begin accepting evidence from approved external manual distribution workflows.

This is not automated delivery. It is a decision about whether an operator may later attach or reference external delivery evidence after using an approved external workflow.

## Decision

Proceed only with an evidence-intake planning baseline.

Do not implement evidence persistence, upload endpoints, journal writes, feedback delivery, campaign dispatch, short links, QR assets, print artifacts, provider calls, voucher mutation, wallet mutation, or money movement in this wave.

## Rationale

Manual copy is operational and accepted. The next safe step is to define what external evidence would mean before building any intake surface.

Evidence intake must not blur ownership:

- External workflows own actual delivery.
- x-feedback owns communication delivery state when wired.
- x-journal owns durable audit facts when explicitly handed off.
- x-action owns workflow continuation state.
- Cockpit may eventually display or collect operator-safe evidence references only through explicit authorization.

## Evidence Intake Must Represent

Future evidence intake may represent:

- Which approved external workflow was used.
- Which recipient was verified.
- Which operator performed the manual handoff.
- When the external workflow was used.
- A redacted delivery reference.
- Operator notes.
- Whether evidence is pending, supplied, rejected, or unavailable.

## Evidence Intake Must Not Represent

Future evidence intake must not represent:

- Lifecycle truth.
- Redemption truth.
- Settlement truth.
- Wallet truth.
- Provider truth.
- x-feedback delivery truth unless x-feedback supplied it.
- x-journal audit truth unless x-journal recorded it.
- x-action completion truth unless x-action supplied it.

## Current Result

`planning-only / no-intake-runtime`

## Next Checkpoint

Cockpit Wave 65B — Manual Distribution External Evidence Schema / Template.
