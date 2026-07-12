# Cockpit Wave 42 — Campaign Recipient Quick Generate Submission Attribution / Result Closure

## Status

Completed.

## Completed checkpoints

- Wave 42A — Campaign Recipient Submission Attribution Audit
- Wave 42B — Campaign Recipient Attribution Response Contract
- Wave 42C — Campaign Recipient Attribution UI Presentation
- Wave 42D — Campaign Recipient Attribution Publish / Browser Verification

## As-built behavior

Campaign-recipient Quick Generate submissions now preserve operator-safe recipient attribution after issuance.

The Quick Generate response includes:

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
- purpose.

The Quick Generate result panel renders the recipient attribution inside the read-only Campaign attribution panel.

Campaign Explorer and Dashboard return links now preserve:

- `campaign_id`
- `campaign_audience_id`
- `campaign_recipient_id`

## Verified behavior

- Backend response contract test passed.
- Frontend result panel test passed.
- Published asset drift check passed.
- Browser smoke verified recipient attribution and recipient-aware return links.

## Preserved boundaries

- No campaign mutation.
- No bulk issuance.
- No delivery dispatch.
- No journal/action/feedback side effects.
- No provider calls outside existing issuance behavior.
- No wallet behavior outside existing `GeneratePayCode` handoff.
- No raw payload exposure.
- `GeneratePayCode` remains the issuance owner.

## Expected UI result

After a campaign-recipient Quick Generate submission succeeds, operators can see:

```text
Campaign attribution
Recipient
Recipient reference
Template
Amount
Generated Pay Code
```

The Campaign Explorer/Dashboard return links keep the recipient context.

## Next recommended wave

`Cockpit Wave 43 — Campaign Recipient Issuance Activity Attribution / Dashboard Surfacing`.

Recommended scope:

- attach campaign recipient attribution to durable operator issuance activity metadata;
- surface campaign-recipient activity on the Cockpit dashboard activity panel;
- keep campaign mutation, bulk issuance, delivery, and lifecycle truth ownership blocked.
