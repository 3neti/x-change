# Cockpit Wave 40A — Campaign Recipient-to-Issuance Draft Field Mapping Audit

## Status

Completed.

## Mission

Define the safe mapping boundary from campaign recipient/source context to Cockpit Quick Generate issuance draft fields.

## Current path

```text
x-campaign recipient/source context
    ↓
CockpitCampaignIssuanceDraftAdapterContract
    ↓
CockpitIssuanceDraftData
    ↓
existing Quick Generate / GeneratePayCode handoff
```

## Gap

Campaign recipient context can express recipient and payout facts with campaign-domain field names. Quick Generate needs a small canonical draft surface:

- `amount`
- `recipient_reference`
- feedback `mobile`
- feedback `email`
- rider `message`
- `purpose`

## Authorized scope

- normalize safe recipient reference aliases;
- normalize safe recipient mobile/email aliases;
- normalize safe recipient/payout amount aliases;
- normalize safe payout purpose/message aliases;
- keep all data as source/prefill metadata only;
- preserve existing Quick Generate and `GeneratePayCode` ownership.

## Explicit non-goals

- no campaign mutation;
- no bulk issuance;
- no delivery dispatch;
- no provider calls;
- no direct wallet access or money movement;
- no journal/action/feedback mutation;
- no new campaign routes or controllers;
- no raw campaign/recipient/provider/wallet payload exposure.

## Next checkpoint

Cockpit Wave 40B — Campaign Recipient Field Normalizer / Draft Adapter.
