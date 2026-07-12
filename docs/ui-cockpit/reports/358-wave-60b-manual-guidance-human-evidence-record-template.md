# Cockpit Wave 60B — Manual Guidance Human Evidence Record Template

## Status

Scaffolded / Ready for human evidence intake.

## Purpose

This template captures human visual evidence for the manual distribution guidance on Cockpit beneficiary URL surfaces.

Use this record after inspecting both:

- Voucher Detail: `/x/cockpit/pay-codes/{code}`
- Distribution Workspace: `/x/cockpit/pay-codes/{code}/distribution`

## Evidence Record

### Reviewer Context

- Reviewer:
- Review date:
- Environment:
- Browser:
- Pay Code:
- Beneficiary URL shown:

### Voucher Detail Evidence

- URL opened:
- Beneficiary URL panel visible: yes / no
- `Manual distribution guidance` visible: yes / no
- Guidance states manual distribution only: yes / no
- Guidance states approved external workflow: yes / no
- Guidance states verify recipient: yes / no
- Guidance states no Cockpit delivery: yes / no
- Guidance states no copy telemetry: yes / no
- Guidance states no short links or QR assets: yes / no
- Guidance states sensitive settlement access material: yes / no
- Notes:

### Distribution Workspace Evidence

- URL opened:
- Beneficiary URL panel visible: yes / no
- `Manual distribution guidance` visible: yes / no
- Guidance states manual distribution only: yes / no
- Guidance states approved external workflow: yes / no
- Guidance states verify recipient: yes / no
- Guidance states no Cockpit delivery: yes / no
- Guidance states no copy telemetry: yes / no
- Guidance states no short links or QR assets: yes / no
- Guidance states sensitive settlement access material: yes / no
- Notes:

### Decision

- Final decision: Pass / Blocked / Fail
- Rationale:
- Observed errors:
- Observed side effects:

## Side Effect Boundary

Completing this record does not authorize:

- SMS, email, webhook, in-app, or campaign delivery.
- Copy telemetry persistence.
- Journal writes.
- Action execution.
- Provider calls.
- Voucher mutation.
- Wallet mutation.
- Short-link generation.
- QR asset generation.
- Money movement.

## Current Result

`pending-human-guidance-intake`

No completed human evidence record has been supplied yet.

## Next Checkpoint

Cockpit Wave 60C — Manual Guidance Acceptance Decision Policy.
