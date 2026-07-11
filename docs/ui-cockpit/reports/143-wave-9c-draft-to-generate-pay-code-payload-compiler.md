# Cockpit Wave 9C — Draft-to-GeneratePayCode Payload Compiler

Status: Implemented / Compiler baseline
Date: 2026-07-11

## Purpose

Compile `CockpitIssuanceDraftData` into the existing `GeneratePayCodeRequest`-compatible payload shape.

## Added

- `CockpitIssuanceDraftCompilerContract`
- `DefaultCockpitIssuanceDraftCompiler`
- Focused compiler tests for simple template issuance and campaign metadata preservation.

## Boundary

The compiler only produces payload data. It does not call `GeneratePayCode`, estimate pricing, check balances, mutate campaigns, write journal entries, send feedback, execute actions, call providers, or move money.

## Next Checkpoint

Cockpit Wave 9D — Quick Generate Compiler Adoption Boundary
