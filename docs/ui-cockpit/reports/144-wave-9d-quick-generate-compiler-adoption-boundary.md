# Cockpit Wave 9D — Quick Generate Compiler Adoption Boundary

Status: Implemented / Runtime seam
Date: 2026-07-11

## Purpose

Bind the Cockpit issuance draft compiler behind a package contract before changing Quick Generate runtime behavior.

## Added

- `CockpitIssuanceDraftCompilerContract` is resolved from the container.
- Default binding targets `DefaultCockpitIssuanceDraftCompiler`.
- Feature tests prove the runtime seam can compile a draft without invoking issuance.

## Boundary

This slice does not change the Quick Generate route, frontend payload builder, `GeneratePayCode`, voucher issuance, wallet behavior, provider calls, campaign behavior, journal writes, action execution, or feedback delivery.

## Next Checkpoint

Cockpit Wave 9E — Template Profile Registry / Resolver Baseline
