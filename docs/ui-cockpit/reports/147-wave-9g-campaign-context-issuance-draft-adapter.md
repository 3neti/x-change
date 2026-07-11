# Cockpit Wave 9G — Campaign Context to Issuance Draft Adapter

Status: Implemented / Adapter baseline
Date: 2026-07-11

## Purpose

Translate campaign planning facts into a Cockpit issuance draft without creating campaign mutation or a new issuance engine.

## Added

- `CockpitCampaignIssuanceDraftAdapterContract`
- `DefaultCockpitCampaignIssuanceDraftAdapter`
- Runtime binding for the adapter
- Tests proving campaign planning context maps to `CockpitIssuanceDraftData`

## Boundary

The adapter is transformation-only. It does not call x-campaign, mutate campaign records, generate Pay Codes, call providers, debit wallets, write journals, execute actions, or send feedback.

## Next Checkpoint

Cockpit Wave 9H — Draft Redaction / Audit Metadata Baseline
