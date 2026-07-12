# Cockpit Wave 37A — Campaign Context Source Link Generation Audit

## Status

Completed.

## Mission

Define the safe scope for generating campaign-aware Quick Generate entry links from Cockpit campaign surfaces.

## Current state

Wave 35 lets `/x/cockpit/quick-generate` accept safe campaign query context and prefill the Quick Generate form.

Wave 36 lets a campaign-prefilled Quick Generate submit return campaign attribution and read-only links back to Campaign Explorer and Dashboard context.

The missing operator path is the source entry point: campaign surfaces do not yet expose a full Quick Generate URL that carries the campaign planning key, execution id, template, amount, recipient reference, and purpose into the already-approved Quick Generate prefill path.

## Gap

Operators currently need to open Quick Generate with manually assembled query parameters to see the `Campaign context prefill` card. Cockpit should generate the source link from campaign read-model context.

## Authorized scope

- Add an operator-safe campaign source link into the existing `campaign_read_model`.
- Point only to the existing read/write Quick Generate handoff route.
- Preserve the current campaign query parameter contract.
- Keep campaign context read-only and prefill-only.
- Keep `GeneratePayCode` as the issuance owner.

## Explicitly excluded

- Campaign mutation.
- Campaign execution.
- Bulk issuance.
- Delivery planning or feedback dispatch.
- Creating new campaign routes or controllers.
- Calling providers outside the existing issuance path.
- Wallet mutation outside the existing issuance path.
- Raw campaign, recipient, provider, wallet, balance, or import payload exposure.
- Bypassing the existing Quick Generate / `GeneratePayCode` handoff.

## Expected UI result

No UI change in this audit slice.

## Next

Cockpit Wave 37B — Campaign Quick Generate Source Link Read Model Contract / Hydration.
