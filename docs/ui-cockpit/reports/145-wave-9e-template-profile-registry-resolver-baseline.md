# Cockpit Wave 9E — Template Profile Registry / Resolver Baseline

Status: Implemented / Registry baseline
Date: 2026-07-11

## Purpose

Make Cockpit template keys functional by resolving them to package-owned issuance template profiles.

## Added

- `CockpitIssuanceTemplateProfileData`
- `CockpitIssuanceTemplateRegistryContract`
- `DefaultCockpitIssuanceTemplateRegistry`
- Runtime binding for the template registry contract
- Tests for known, disabled, and unknown templates

## Boundary

Templates are profiles only in this slice. They do not issue Pay Codes, mutate campaigns, call providers, read or debit wallets, write journals, execute actions, or send feedback.

## Next Checkpoint

Cockpit Wave 9F — Template Constraints and Draft Validation Baseline
