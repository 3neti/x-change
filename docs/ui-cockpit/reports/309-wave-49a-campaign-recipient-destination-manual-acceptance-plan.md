# Cockpit Wave 49A — Campaign Recipient Destination Manual Acceptance Plan

## Status

Completed.

## Mission

Define the manual acceptance checkpoint for campaign-aware destination navigation.

The operator path under review is:

```text
Dashboard activity card
    → Pay Code Detail
    → Distribution Workspace
    → return links back to Detail / Explorer / Dashboard
```

## Acceptance objectives

- Confirm the destination context copy is understandable to an operator.
- Confirm campaign context is visibly preserved on Pay Code Detail.
- Confirm campaign context is visibly preserved on Distribution Workspace.
- Confirm return links are clearly read-only.
- Confirm the flow does not imply campaign mutation, dispatch, delivery, journal writes, provider calls, wallet movement, or lifecycle truth ownership.

## Evidence expected

- Browser automation result for the campaign activity navigation path.
- Human/manual review notes when available.
- Explicit pass, blocked, or follow-up decision.

## Boundary

This checkpoint does not authorize new Cockpit mutations.

No backend runtime, provider integration, wallet movement, feedback delivery, journal writes, action execution, campaign mutation, or distribution dispatch is added.

## Next slice

`Cockpit Wave 49B — Campaign Recipient Destination Automated Evidence Check`
