# Cockpit Wave 64A — Manual Distribution Operator Runbook

## Status

Scaffolded / Operator runbook recorded.

## Purpose

Wave 64A defines the operator runbook for manually distributing a beneficiary Pay Code URL from Cockpit.

This runbook translates the accepted UI guidance into an operational workflow without adding delivery automation, telemetry, artifact generation, or mutation behavior.

## Preconditions

Before sharing a beneficiary URL, the operator must confirm:

- The Pay Code is the intended Pay Code.
- The beneficiary URL is visible on Voucher Detail or Distribution Workspace.
- The URL uses the expected host and claim path.
- The recipient has been verified through an approved external workflow.
- The external workflow is approved by the organization.
- The operator understands Cockpit will not send the message.

## Manual Distribution Steps

1. Open Voucher Detail or Distribution Workspace for the Pay Code.
2. Confirm the displayed beneficiary URL.
3. Confirm the manual distribution guidance is visible.
4. Click `Copy beneficiary URL`.
5. Confirm the copy status remains local and says no delivery was sent.
6. Paste the URL only into the approved external workflow.
7. Verify the recipient before sending through the external workflow.
8. Do not paste the URL into unapproved channels.

## Operator Safety Rules

- Treat beneficiary URLs as sensitive settlement access material.
- Do not send the URL to unverified recipients.
- Do not use personal or unapproved channels.
- Do not infer lifecycle truth from the copy action.
- Do not treat copy as delivery confirmation.
- Do not assume copy creates a journal record.
- Do not assume copy creates feedback, action, or campaign records.

## Cockpit Boundary

Cockpit manual copy remains:

- Browser-local.
- Non-persistent.
- Non-delivery.
- Non-telemetry.
- Non-journaled.
- Non-action-executing.
- Non-provider-calling.
- Non-voucher-mutating.
- Non-wallet-mutating.
- Non-artifact-generating.
- Non-money-moving.

## Escalation Conditions

Stop and escalate before sharing if:

- The Pay Code or beneficiary URL looks wrong.
- The recipient cannot be verified.
- The operator is unsure whether the external workflow is approved.
- The copy status shows failure or unavailable.
- The page appears stale or mismatched.
- The URL is exposed to the wrong person or channel.

## Next Checkpoint

Cockpit Wave 64B — Manual Distribution Workflow Handoff Boundary.
