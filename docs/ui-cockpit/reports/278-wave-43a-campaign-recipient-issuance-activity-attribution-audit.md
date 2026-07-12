# Cockpit Wave 43A — Campaign Recipient Issuance Activity Attribution Audit

## Status

Completed.

## Mission

Define the boundary for carrying campaign-recipient Quick Generate attribution into durable operator issuance activity and the Cockpit dashboard activity panel.

## Current state

- Wave 42 preserves campaign-recipient attribution in the Quick Generate response.
- Quick Generate can record durable operator issuance activity after the existing `GeneratePayCode` handoff.
- Durable activity stores safe context and metadata, then presents read-only activity cards on the Cockpit dashboard.
- The dashboard activity card currently shows Pay Code, handoff status, journal/action/feedback summaries, and links, but does not surface campaign-recipient attribution.

## Gap

Campaign-recipient Quick Generate activity should remain inspectable from the dashboard after issuance. Operators should be able to see which campaign, audience, recipient, template, amount, and recipient reference were associated with the generated Pay Code without inspecting raw campaign or recipient payloads.

## Authorized scope

Wave 43 may add:

- operator-safe campaign attribution metadata to durable operator issuance activity records;
- read-model/presenter propagation of that attribution into activity presentations;
- dashboard UI rendering for safe campaign attribution inside the operator issuance activity card;
- frontend and package verification;
- published asset verification;
- documentation and Compass updates.

## Explicit boundaries

Wave 43 must not add:

- campaign mutation;
- recipient issued/progress state changes;
- bulk issuance;
- delivery dispatch;
- lifecycle truth ownership;
- new journal/action/feedback side effects beyond existing activity handoff configuration;
- provider calls;
- wallet movement outside the existing `GeneratePayCode` handoff;
- raw campaign, recipient, wallet, provider, cost, debit, allocation, or idempotency payload rendering;
- a new issuance runtime outside `GeneratePayCode`.

## Architectural decision

Campaign-recipient issuance activity attribution is read-only dashboard evidence. It is not campaign progress state, not lifecycle truth, and not a trigger for campaign mutation.

## Next checkpoint

`Cockpit Wave 43B — Campaign Recipient Activity Metadata Handoff`.
