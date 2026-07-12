# Cockpit Wave 45A — Campaign Recipient Activity Detail / Distribution Context Audit

## Status

Completed.

## Mission

Define the boundary for preserving safe campaign-recipient context when an operator opens Pay Code detail or Distribution workspace from a campaign-attributed Operator Issuance Activity card.

## Current state

- Wave 44 preserves campaign-recipient context when opening Pay Code Explorer or returning to Campaign Dashboard from an activity card.
- Activity cards still open Pay Code detail with only the Pay Code route.
- Activity cards do not expose a direct Distribution workspace link.

## Gap

When an activity card represents campaign-recipient Quick Generate issuance, detail and distribution navigation should preserve the same safe campaign context used by Explorer navigation:

- planning key;
- execution id;
- campaign id;
- audience id;
- recipient id;
- campaign source;
- generated activity code.

## Authorized scope

Wave 45 may add:

- campaign-recipient query context to activity Pay Code detail links;
- a read-only activity Distribution workspace link;
- frontend tests proving detail/distribution links preserve campaign context;
- published asset and browser verification;
- documentation and Compass updates.

## Explicit boundaries

Wave 45 must not add:

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

Campaign-recipient activity detail/distribution navigation is read-only context propagation. Detail and Distribution remain evidence/operation surfaces, not campaign progress writers.

## Next checkpoint

`Cockpit Wave 45B — Campaign Recipient Activity Detail / Distribution Link Hydration`.
