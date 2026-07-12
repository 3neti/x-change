# Cockpit Wave 42B — Campaign Recipient Attribution Response Contract

## Status

Completed.

## What changed

Quick Generate campaign attribution now includes operator-safe recipient submission facts:

- `template_key`
- `amount`
- `currency`
- `recipient_reference`
- `purpose`

Post-issuance campaign return links now carry recipient-aware query context:

- `campaign_id`
- `campaign_audience_id`
- `campaign_recipient_id`

Campaign return navigation items also include safe metadata for planning, execution, campaign, audience, recipient, source, and read-only/no-mutation semantics.

## Safety constraints

- Attribution remains read-only evidence.
- Campaign state is not mutated.
- Bulk issuance is not introduced.
- Delivery, journal, action, and feedback handoffs are not executed.
- Unsafe request, campaign, recipient, provider, wallet, debit, cost, allocation, raw, and idempotency payloads remain excluded.
- Issuance remains owned by `GeneratePayCode`.

## Test coverage

- `CockpitQuickGenerateCampaignAttributionResponseTest` now verifies safe recipient submission fields and recipient-aware campaign return links.

## Expected UI result

No UI change yet. Vue result presentation lands in Wave 42C.

## Next checkpoint

`Cockpit Wave 42C — Campaign Recipient Attribution UI Presentation`.
