# Cockpit Wave 60A — Manual Distribution Link Human Guidance Acceptance Plan

## Status

Scaffolded / Pending human guidance intake.

## Purpose

This checkpoint defines how a human reviewer accepts the manual distribution guidance added to the Cockpit beneficiary URL surfaces.

The guidance acceptance is visual and comprehension-oriented. It does not authorize delivery, persistence, campaign dispatch, journal writes, action execution, provider calls, voucher mutation, wallet mutation, short-link generation, QR generation, or money movement.

## Surfaces to Inspect

1. Voucher Detail:
   - `/x/cockpit/pay-codes/{code}`

2. Distribution Workspace:
   - `/x/cockpit/pay-codes/{code}/distribution`

Use an existing Pay Code with a visible beneficiary URL.

## Required Visible Guidance

Both surfaces must clearly communicate:

- Manual distribution only.
- Use an approved external workflow.
- Verify the recipient before sharing.
- Cockpit does not send SMS, email, webhook, in-app, or campaign delivery.
- Cockpit does not record copy telemetry.
- Cockpit does not create short links or QR assets.
- Beneficiary URLs are sensitive settlement access material.

## Human Checklist

For each surface:

- Open the page.
- Confirm the beneficiary URL panel is visible.
- Confirm `Manual distribution guidance` is visible.
- Confirm the guidance can be understood without reading engineering reports.
- Confirm no UI text implies Cockpit-delivered SMS, email, webhook, in-app notification, or campaign distribution.
- Confirm no UI text implies copy-event telemetry, short-link creation, QR asset creation, journal writes, action execution, provider calls, voucher mutation, wallet mutation, or money movement.

## Decision States

- Pass: both surfaces show clear, safe manual distribution guidance.
- Blocked: the reviewer cannot inspect both surfaces or no usable Pay Code is available.
- Fail: guidance is missing, misleading, unsafe, or implies behavior that Cockpit does not own.

## Current Result

`pending-human-guidance-intake`

No human evidence has been supplied yet for this guidance acceptance checkpoint.

## Next Checkpoint

Cockpit Wave 60B — Manual Guidance Human Evidence Record Template.
