# Cockpit Wave 43B — Campaign Recipient Activity Metadata Handoff

## Status

Completed.

## What changed

- Quick Generate durable operator issuance activity metadata now includes safe `campaign_attribution` when the successful issuance response contains campaign-recipient attribution.
- The activity metadata preserves campaign planning, execution, campaign, audience, recipient, source, generated Pay Code, template, amount, currency, recipient reference, and purpose.
- The metadata explicitly marks attribution as read-only and non-mutating.

## Tests

- Added a combined-runtime feature test proving campaign-recipient attribution is recorded in durable activity metadata.
- Added an architecture report guard for this checkpoint.

## Boundaries preserved

- No campaign mutation.
- No campaign progress update.
- No bulk issuance.
- No delivery dispatch.
- No lifecycle truth ownership.
- No provider calls outside existing issuance behavior.
- No wallet behavior outside existing `GeneratePayCode` handoff.
- No raw campaign, recipient, wallet, provider, cost, debit, allocation, or idempotency payload rendering.

## Expected UI result

No visible UI change yet. The safe campaign attribution now exists in durable activity metadata for the next presentation slice to surface on the dashboard.

## Next checkpoint

`Cockpit Wave 43C — Campaign Recipient Activity Dashboard Presentation`.
