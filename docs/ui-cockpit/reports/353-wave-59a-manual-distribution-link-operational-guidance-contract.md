# Cockpit Wave 59A — Manual Distribution Link Operational Guidance Contract

## Status

Completed on 2026-07-12.

## Purpose

Define the operator guidance that should accompany accepted manual beneficiary URL copy UX.

## Guidance Contract

Operator-facing help text must communicate:

- the copied link is for manual distribution only
- the operator should send the link only through an approved external workflow
- Cockpit does not send SMS, email, webhook, in-app notification, or campaign delivery from this panel
- Cockpit does not record copy telemetry
- Cockpit does not create short links or QR assets in this panel
- the beneficiary URL should be treated as sensitive settlement access material
- the operator should verify the recipient before sharing

## Accepted Surfaces

Guidance must appear on:

- Voucher Detail beneficiary URL panel
- Distribution Workspace beneficiary URL panel

## Boundary

This wave may change operator help text only.

It must not add:

- backend endpoints
- copy event persistence
- feedback delivery
- campaign dispatch
- journal writes
- action execution
- provider calls
- voucher mutation
- wallet mutation
- money movement

## Verification

- `php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave59aManualDistributionLinkOperationalGuidanceContractTest.php`

## Next

Cockpit Wave 59B — Voucher Detail Operational Guidance Text.
