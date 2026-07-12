# Cockpit Wave 39A — Campaign Plan-to-Issuance Draft Template Mapping Audit

## Status

Completed.

## Mission

Define the safe mapping boundary from campaign planning intent to Cockpit Quick Generate issuance drafts.

## Current architecture

The existing path is:

```text
x-campaign read model / source context
    ↓
campaign_read_model.quick_generate_link
    ↓
/x/cockpit/quick-generate?...campaign_*
    ↓
CockpitCampaignIssuanceDraftAdapterContract
    ↓
CockpitIssuanceDraftData
    ↓
existing GeneratePayCode handoff
```

## Gap

Campaign plan/source context can express template intent using product or campaign-domain language. Quick Generate currently expects Cockpit template keys such as:

- `money-changer`
- `ofw-remittance`
- `settlement-envelope`

Wave 39 should normalize safe campaign template intent into those existing Quick Generate template keys without adding a campaign issuance runtime.

## Authorized scope

- normalize known campaign template intent aliases;
- preserve explicit Cockpit template keys when supplied;
- keep unknown/deferred intent safe and non-mutating;
- pass normalized template keys through existing source-link and Quick Generate prefill paths;
- update docs, tests, and compass.

## Explicit non-goals

- no campaign mutation;
- no bulk issuance;
- no delivery dispatch;
- no provider calls;
- no direct wallet access or money movement;
- no journal/action/feedback mutation;
- no new campaign routes or controllers;
- no replacement of `GeneratePayCode`;
- no raw campaign/recipient/provider/wallet payload exposure.

## Next checkpoint

Cockpit Wave 39B — Campaign Template Intent Normalizer / Draft Adapter.
