# Cockpit Wave 43 — Campaign Recipient Issuance Activity Attribution / Dashboard Surfacing

## Status

Completed.

## Completed checkpoints

- Wave 43A — Campaign Recipient Issuance Activity Attribution Audit
- Wave 43B — Campaign Recipient Activity Metadata Handoff
- Wave 43C — Campaign Recipient Activity Dashboard Presentation
- Wave 43D — Campaign Recipient Activity Publish / Browser Verification

## As-built behavior

Campaign-recipient Quick Generate activity now carries operator-safe campaign attribution into durable operator issuance activity and can surface it on the Cockpit dashboard.

The durable activity metadata can include:

- planning key;
- execution id;
- campaign id;
- audience id;
- recipient id;
- source;
- generated Pay Code;
- template key;
- amount;
- currency;
- recipient reference;
- purpose;
- read-only / non-mutating flags;
- campaign-attribution-only redaction policy.

The dashboard activity card can now render:

```text
Campaign attribution
Campaign
Audience
Recipient
Recipient reference
Template
Amount
Generated Pay Code
Planning key
Execution
Source
Purpose
Campaign mutation: no
Read-only: yes
```

## Verified behavior

- Feature coverage proves campaign-recipient attribution is recorded in durable activity metadata.
- Presenter coverage proves unsafe fields are dropped before presentation.
- Frontend coverage proves the dashboard card renders safe campaign attribution and suppresses unsafe payload markers.
- Published asset drift check passed.
- Browser smoke for campaign-prefilled Quick Generate passed.

## Preserved boundaries

- No campaign mutation.
- No campaign progress update.
- No bulk issuance.
- No delivery dispatch.
- No lifecycle truth ownership.
- No provider calls outside existing issuance behavior.
- No wallet behavior outside existing `GeneratePayCode` handoff.
- No raw campaign, recipient, wallet, provider, cost, debit, allocation, or idempotency payload rendering.
- `GeneratePayCode` remains the issuance owner.

## Expected UI result

When a campaign-recipient Quick Generate issuance has durable activity metadata, `/x/cockpit` Operator Issuance Activity can show a read-only Campaign attribution section on the activity card.

## Next recommended wave

`Cockpit Wave 44 — Campaign Recipient Activity Context Navigation / Explorer Bridge`.

Recommended scope:

- use safe activity campaign attribution to build recipient-aware dashboard activity navigation;
- preserve campaign context when opening Pay Code Explorer from an activity card;
- optionally expose a read-only return-to-campaign-dashboard activity link;
- keep campaign mutation, bulk issuance, delivery, lifecycle truth ownership, and unsafe payload exposure blocked.
