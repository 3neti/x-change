# Cockpit Wave 36A — Campaign-Sourced Result Attribution / Explorer Bridge Audit

## Status

Completed.

## Mission

Define the safe scope for carrying campaign attribution from a campaign-prefilled Quick Generate submit into the operator result and post-issuance handoff.

## Current state

Wave 35 lets Quick Generate accept campaign context through query parameters, hydrate it into the read model, prefill the form, and submit read-only campaign metadata through the existing issuance handoff.

The successful result currently exposes generic post-issuance destinations:

- Cockpit voucher detail.
- Cockpit distribution workspace.

## Gap

The result does not yet expose campaign-aware return destinations. Operators who start from campaign context need a safe way back to campaign-filtered Cockpit surfaces after issuance.

## Authorized scope

- Preserve campaign attribution in the operator-safe Quick Generate response.
- Add read-only campaign-aware return links to the post-issuance handoff.
- Use existing Cockpit route query parameters.
- Keep `GeneratePayCode` as the issuance owner.

## Explicitly excluded

- Campaign mutation.
- Campaign execution.
- Bulk issuance.
- Delivery planning or feedback dispatch.
- Provider calls outside the existing issuance path.
- Wallet mutation outside the existing issuance path.
- Raw campaign, recipient, provider, wallet, or balance payload exposure.

## Next

Cockpit Wave 36B — Campaign Attribution Response Contract / Backend Handoff Links.
