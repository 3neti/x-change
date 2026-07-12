# Cockpit Wave 63A — Manual Copy Operational Hardening Contract

## Status

Scaffolded / Hardening contract recorded.

## Purpose

Wave 63A defines the operational hardening requirements for the accepted manual copy capability.

The goal is to keep manual distribution useful while preventing accidental drift into automated distribution, telemetry, artifact generation, or mutation behavior.

## Hardening Requirements

Manual copy must remain:

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

## Required Regression Guards

The manual copy regression guard must prove:

- Successful copy writes only to `navigator.clipboard.writeText`.
- Missing clipboard support does not call backend APIs.
- Clipboard rejection does not call backend APIs.
- Missing values disable the copy control.
- Copy does not call `fetch`.
- Copy does not call `navigator.sendBeacon`.
- Copy does not create or use `XMLHttpRequest`.

## Accepted UI Surfaces

The hardening applies to:

- Voucher Detail.
- Distribution Workspace.

## Explicit Non-Goals

Wave 63 does not add:

- Copy event persistence.
- Delivery through x-feedback.
- Campaign dispatch.
- Short-link generation.
- QR asset generation.
- Print artifact generation.
- Journal writes.
- Action execution.
- Provider calls.
- Voucher mutation.
- Wallet mutation.
- Money movement.

## Next Checkpoint

Cockpit Wave 63B — Manual Copy No-Backend-Interaction Regression Guard.
