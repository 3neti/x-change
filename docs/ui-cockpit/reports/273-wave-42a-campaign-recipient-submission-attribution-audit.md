# Cockpit Wave 42A — Campaign Recipient Submission Attribution Audit

## Status

Completed.

## Mission

Define the boundary for preserving recipient-level campaign attribution after a campaign-recipient Quick Generate source link is submitted.

## Current state

- Wave 41 exposes safe recipient-level Campaign → Quick Generate source links through `campaign_read_model.recipient_quick_generate_links`.
- Selecting a recipient link opens Quick Generate with campaign context.
- Quick Generate response attribution already includes campaign planning, execution, campaign, audience, recipient id, source, and generated code.
- Post-issuance navigation already returns read-only campaign Dashboard and Explorer links.

## Gap

Recipient-level source-link submission should preserve enough operator-safe recipient context in the Quick Generate result for the operator to verify which campaign recipient was issued without inspecting raw payloads.

## Authorized scope

Wave 42 may add:

- operator-safe recipient submission facts to `campaign_attribution`;
- recipient-aware query parameters to post-issuance Campaign Explorer and Dashboard links;
- UI presentation of recipient attribution in the Quick Generate result panel;
- browser verification for recipient-source-link submit flow;
- documentation and Compass updates.

## Explicit boundaries

Wave 42 must not add:

- campaign mutation;
- bulk issuance;
- delivery dispatch;
- journal/action/feedback side effects;
- provider calls;
- wallet movement outside the existing `GeneratePayCode` handoff;
- raw campaign, recipient, wallet, provider, cost, debit, allocation, or idempotency payload rendering;
- a new issuance runtime outside `GeneratePayCode`.

## Architectural decision

Recipient attribution is read-only result evidence. It does not mark campaign recipients as issued, does not update campaign progress, and does not become lifecycle truth.

## Next checkpoint

`Cockpit Wave 42B — Campaign Recipient Attribution Response Contract`.
