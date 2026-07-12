# Cockpit Wave 64B — Manual Distribution Workflow Handoff Boundary

## Status

Scaffolded / Workflow handoff boundary recorded.

## Purpose

Wave 64B defines where Cockpit responsibility ends and the approved external distribution workflow begins.

Cockpit presents and copies the beneficiary URL. The external workflow is responsible for actual delivery, recipient communication, and any delivery-specific evidence.

## Cockpit Responsibilities

Cockpit may:

- Display the canonical beneficiary Pay Code URL.
- Provide browser-local copy.
- Show manual distribution guidance.
- Remind the operator to verify the recipient.
- Remind the operator to use an approved external workflow.

## External Workflow Responsibilities

The approved external workflow owns:

- Recipient verification.
- Channel selection.
- Message composition.
- Message sending.
- Delivery evidence.
- Any delivery records.
- Any channel-specific audit requirements.
- Any retry or escalation process.

## Handoff Rules

- Copying the URL is not delivery.
- Copying the URL is not delivery confirmation.
- Copying the URL is not feedback state.
- Copying the URL is not campaign state.
- Copying the URL is not journal state.
- Copying the URL is not action state.
- Copying the URL is not lifecycle truth.
- Copying the URL is not money movement.

## Not Authorized in Cockpit

Cockpit remains prohibited from:

- Sending SMS, email, webhook, in-app, or campaign messages from manual copy panels.
- Persisting copy telemetry.
- Creating short links.
- Generating QR assets.
- Generating print artifacts.
- Writing journal entries.
- Executing actions.
- Calling providers.
- Mutating vouchers.
- Mutating wallets.
- Moving money.

## Future Integration Boundary

If automated delivery is later desired, it must be implemented through the correct owning layer:

- x-feedback for communication delivery and delivery records.
- x-campaign for campaign/program dispatch.
- x-journal for durable audit facts.
- x-action for operator workflow continuation.

Cockpit must consume those capabilities through explicit APIs or contracts. It must not invent hidden delivery behavior.

## Next Checkpoint

Cockpit Wave 64C — Manual Distribution Operator Runbook / Workflow Handoff Closure.
