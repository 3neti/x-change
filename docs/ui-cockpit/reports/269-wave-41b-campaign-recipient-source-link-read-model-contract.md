# Cockpit Wave 41B — Campaign Recipient Source-Link Read Model Contract / Hydration

## Status

Completed.

## What changed

- Added `campaign_read_model.recipient_quick_generate_links`.
- Hydrated recipient-level Quick Generate links from safe campaign adapter metadata collections.
- Supported safe metadata keys:
  - `metadata.recipient_quick_generate_contexts`
  - `metadata.quick_generate_recipients`
  - `metadata.recipients`
  - `cards.campaign.recipient_quick_generate_contexts`
  - `cards.campaign.quick_generate_recipients`
  - `cards.campaign.recipients`
- Reused the existing campaign source-link and campaign issuance draft adapter normalization.

## Safety constraints

- Recipient links are capped to the first 10 safe contexts.
- Recipient links remain read-only source links.
- Recipient links use the existing Quick Generate route.
- Recipient links do not mutate campaign state.
- Recipient links do not generate Pay Codes automatically.
- Recipient links do not expose raw campaign, recipient, wallet, provider, delivery, mobile, or email payloads.
- Quick Generate submission remains owned by the existing `GeneratePayCode` handoff.

## Test coverage

- `CockpitCampaignQuickGenerateSourceLinkTest` now verifies multiple recipient source links from adapter metadata.
- `CockpitReadModelBaselineTest` now includes the empty default `recipient_quick_generate_links` contract.

## Expected UI result

No visible UI change yet. Vue presentation lands in Wave 41C.

## Next checkpoint

`Cockpit Wave 41C — Campaign Recipient Source-Link UI Presentation`.
