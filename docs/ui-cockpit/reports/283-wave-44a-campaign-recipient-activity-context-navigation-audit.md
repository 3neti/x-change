# Cockpit Wave 44A — Campaign Recipient Activity Context Navigation Audit

## Status

Completed.

## Mission

Define the boundary for using safe campaign-recipient activity attribution to preserve campaign context when an operator navigates from a dashboard activity card into the Pay Code Explorer.

## Current state

- Wave 43 records safe campaign-recipient attribution in durable operator issuance activity metadata.
- Wave 43 surfaces that attribution in the dashboard Operator Issuance Activity card.
- The existing activity card `Open in Explorer` link only carries activity code/source context.

## Gap

When a dashboard activity card represents a campaign-recipient Quick Generate issuance, the Explorer navigation should preserve campaign context:

- planning key;
- execution id;
- campaign id;
- audience id;
- recipient id;
- campaign source;
- generated activity code.

This lets the operator inspect the generated Pay Code in campaign context without mutating campaign state.

## Authorized scope

Wave 44 may add:

- recipient-aware Explorer query construction from safe campaign attribution;
- optional read-only Campaign Dashboard return link from the activity card;
- frontend tests proving campaign query preservation and unsafe payload suppression;
- published asset and browser verification;
- documentation and Compass updates.

## Explicit boundaries

Wave 44 must not add:

- campaign mutation;
- recipient issued/progress state changes;
- bulk issuance;
- delivery dispatch;
- lifecycle truth ownership;
- provider calls;
- wallet movement;
- new issuance runtime behavior;
- unsafe campaign, recipient, wallet, provider, cost, debit, allocation, or idempotency payload exposure.

## Architectural decision

Campaign-recipient activity navigation is read-only context propagation. It is not campaign state, not campaign progress, and not a trigger for campaign mutation.

## Next checkpoint

`Cockpit Wave 44B — Campaign Recipient Activity Explorer Link Hydration`.
