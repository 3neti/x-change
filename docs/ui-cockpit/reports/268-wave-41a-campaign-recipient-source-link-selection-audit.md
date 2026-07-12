# Cockpit Wave 41A — Campaign Recipient Source-Link Selection Audit

## Status

Completed.

## Mission

Define the read-only boundary for exposing campaign recipient-level operator entry points into the existing Quick Generate handoff.

## Current state

- `campaign_read_model.quick_generate_link` supports one safe Campaign → Quick Generate link.
- Wave 40 normalized campaign recipient and payout fields into that single link.
- The Campaign Cockpit Adoption panel can render the single link as `Open Quick Generate`.
- Quick Generate remains owned by the existing `GeneratePayCode` handoff.

## Gap

Campaign operators need recipient-level entry points when the campaign read model contains multiple safe recipient draft contexts. Without this, a campaign surface can only expose one generic source link even when the read model has enough safe recipient facts to prefill separate Quick Generate drafts.

## Authorized scope

Wave 41 may add:

- a read-model array of safe recipient Quick Generate links;
- source-context extraction from safe campaign metadata such as recipient draft collections;
- Vue presentation for recipient entry points;
- published asset and browser/fixture verification;
- documentation and Compass updates.

## Explicit boundaries

Wave 41 must not add:

- campaign mutation;
- bulk issuance;
- automatic Pay Code generation;
- file import execution;
- feedback delivery;
- provider calls;
- wallet reservation/debit/transfer;
- journal/action/feedback side effects;
- unsafe raw campaign, recipient, wallet, provider, or delivery payload rendering;
- a new issuance runtime outside `GeneratePayCode`.

## Architectural decision

Recipient links are operator-safe source links only. They may prefill Quick Generate through query parameters and campaign context metadata, but selecting a recipient still requires the operator to submit through the existing Quick Generate form and existing x-change issuance handoff.

## Next checkpoint

`Cockpit Wave 41B — Campaign Recipient Source-Link Read Model Contract / Hydration`.
