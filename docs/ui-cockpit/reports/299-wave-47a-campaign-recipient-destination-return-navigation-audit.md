# Cockpit Wave 47A — Campaign Recipient Destination Return Navigation Audit

## Purpose

Define the boundary for return navigation from campaign-aware Pay Code Detail and Distribution Workspace pages.

## Current state

- Wave 45 preserved safe campaign-recipient context from dashboard Operator Issuance Activity cards into Pay Code Detail and Distribution Workspace links.
- Wave 46 rendered safe campaign-recipient context cards on Pay Code Detail and Distribution Workspace.
- Destination pages do not yet expose operator return links back to campaign-aware Explorer or Dashboard context.

## Target

When a destination page has safe `campaign_navigation_context`, it may render read-only return links:

- Pay Code Detail:
  - return to Campaign-aware Explorer;
  - return to Campaign Dashboard.
- Distribution Workspace:
  - return to Pay Code Detail;
  - return to Campaign-aware Explorer;
  - return to Campaign Dashboard.

## Query context to preserve

- `campaign_planning_key`;
- `campaign_execution_id`;
- `campaign_id`;
- `campaign_audience_id`;
- `campaign_recipient_id`;
- `campaign_source`;
- `activity_code` and `activity_source` only for Explorer return links.

## Explicit boundaries

- No campaign mutation.
- No campaign routes/controllers.
- No bulk issuance.
- No distribution dispatch.
- No feedback delivery.
- No journal writes.
- No provider calls.
- No wallet movement.
- No lifecycle truth ownership.
- No unsafe payload rendering.

## Checkpoints

- Wave 47B — Pay Code Detail Campaign Return Navigation
- Wave 47C — Distribution Workspace Campaign Return Navigation
- Wave 47D — Campaign Destination Return Navigation Publish / Browser Verification
- Wave 47E — Campaign Recipient Destination Return Navigation Closure
