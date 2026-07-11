# Cockpit Wave 9F — Template Constraints and Draft Validation Baseline

Status: Implemented / Validation baseline
Date: 2026-07-11

## Purpose

Validate Cockpit issuance drafts against template availability and minimum GeneratePayCode-compatible input requirements before runtime issuance adoption.

## Added

- `CockpitIssuanceDraftValidationResultData`
- `CockpitIssuanceDraftValidatorContract`
- `DefaultCockpitIssuanceDraftValidator`
- Runtime binding for the validator
- Tests for valid templates, unknown templates, disabled templates, and missing amount

## Boundary

Validation is preflight only. This slice does not call `GeneratePayCode`, estimate pricing, debit wallets, mutate campaigns, write journals, execute actions, send feedback, call providers, or move money.

## Next Checkpoint

Cockpit Wave 9G — Campaign Context to Issuance Draft Adapter
