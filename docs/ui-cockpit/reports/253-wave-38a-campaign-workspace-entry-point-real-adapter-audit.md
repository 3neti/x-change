# Cockpit Wave 38A — Campaign Workspace Entry Point Real Adapter Audit

## Status

Completed.

## Mission

Define the safe scope for consuming real x-campaign Cockpit workspace metadata as source context for Quick Generate entry links.

## Current state

Wave 37 added `campaign_read_model.quick_generate_link` from dashboard query parameters.

x-campaign exposes a read-only `CampaignCockpitWorkspace::summary(...)` contract that returns operator-safe campaign summary data, cards, panels, actions, blockers, metadata, and no-side-effect persistence flags.

## Gap

The source link currently uses only x-change route query context. It does not yet consume real x-campaign summary metadata when the campaign package can provide a recommended Quick Generate draft or source context.

## Authorized scope

- Allow x-change to read safe Quick Generate source context from x-campaign Cockpit summary metadata.
- Preserve the existing `campaign_read_model.quick_generate_link` contract.
- Prefer explicit dashboard query values over adapter metadata when both are present.
- Keep `CampaignCockpitWorkspace::summary(...)` read-only.
- Keep `/x/cockpit/quick-generate` as the target route.
- Keep `GeneratePayCode` as the issuance owner.

## Explicitly excluded

- Campaign mutation.
- Campaign execution.
- Bulk issuance.
- Delivery planning or feedback dispatch.
- Creating x-change campaign mutation routes.
- Creating x-campaign routes/controllers/resources from x-change.
- Provider calls outside the existing issuance path.
- Wallet mutation outside the existing issuance path.
- Raw campaign, recipient, provider, wallet, balance, import, or generation payload exposure.

## Expected UI result

No UI change in this audit slice.

## Next

Cockpit Wave 38B — Campaign Adapter Source Context Normalization.
