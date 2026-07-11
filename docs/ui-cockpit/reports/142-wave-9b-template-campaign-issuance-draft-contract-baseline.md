# Cockpit Wave 9B — Template/Campaign Issuance Draft Contract Baseline

Status: Implemented / Contract baseline
Date: 2026-07-11

## Purpose

Introduce a package-local functional draft contract that can represent Cockpit template issuance and future campaign-backed issuance before compiling anything into `GeneratePayCode`.

## Added

- `CockpitIssuanceDraftData`
- `CockpitIssuanceCampaignContextData`
- Focused tests proving simple Quick Generate drafts and campaign-backed draft context can be represented without execution.

## Boundary

This slice does not call `GeneratePayCode`, debit wallets, call providers, mutate campaigns, write journal entries, send feedback, execute x-action, or change voucher behavior.

## Next Checkpoint

Cockpit Wave 9C — Draft-to-GeneratePayCode Payload Compiler
