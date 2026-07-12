# Cockpit Wave 43C — Campaign Recipient Activity Dashboard Presentation

## Status

Completed.

## What changed

- Durable activity read-model hydration now carries safe campaign attribution from persisted activity metadata into activity presentation.
- The default operator issuance activity presenter includes sanitized `campaign_attribution` metadata when available.
- The Cockpit dashboard operator issuance activity card now renders a read-only Campaign attribution section.

## UI effect

When durable activity contains campaign-recipient attribution, `/x/cockpit` can show the generated Pay Code activity with:

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

## Tests

- Unit presenter test covers safe campaign-recipient attribution metadata.
- Frontend dashboard hydration test covers the rendered activity attribution section and unsafe-payload suppression.
- Architecture report guard covers the checkpoint.

## Boundaries preserved

- Campaign attribution is presentation-only.
- Campaign state is not mutated.
- Recipient progress is not updated.
- Bulk issuance is not triggered.
- Delivery is not dispatched.
- Lifecycle truth remains outside Cockpit activity presentation.
- Raw campaign, recipient, wallet, provider, cost, debit, allocation, and idempotency payloads are not rendered.

## Next checkpoint

`Cockpit Wave 43D — Campaign Recipient Activity Publish / Browser Verification`.
