# Cockpit Wave 46A — Campaign Recipient Detail / Distribution Context Rendering Audit

## Purpose

Define the implementation boundary for rendering safe campaign-recipient navigation context on the Pay Code Detail and Distribution Workspace destination pages.

## Current state

- Wave 45 preserves safe campaign-recipient query context when an Operator Issuance Activity card links to Pay Code Detail and Distribution Workspace.
- Pay Code Explorer already has a read-only `campaign_navigation_context` prop and visible presentation card.
- Pay Code Detail and Distribution Workspace do not yet accept or render `campaign_navigation_context`.
- Pay Code Detail and Distribution Workspace controllers currently accept only the route `{code}` parameter.

## Wave 46 target

Pay Code Detail and Distribution Workspace should visibly render a sanitized campaign-recipient context panel when the request contains safe campaign context query parameters.

Required visible facts:

- planning key;
- execution ID;
- destination;
- read-only mutation reason;
- redaction payload policy.

## Authorized scope

- Add read-only query parsing to Pay Code Detail and Distribution Workspace controllers.
- Add `campaign_navigation_context` to the existing Inertia page props.
- Render safe campaign context cards on Pay Code Detail and Distribution Workspace.
- Reuse the Explorer semantics:
  - `schema: x-change.cockpit.campaign-navigation.v1`;
  - `authorized: true`;
  - `read_only: true`;
  - `mutation.enabled: false`;
  - `mutation.status: blocked`;
  - `mutation.reason: campaign-navigation-read-only`;
  - `redactions.payloads: navigation-context-only`.

## Explicit non-goals

- No campaign mutation.
- No campaign routes/controllers.
- No bulk issuance.
- No distribution dispatch.
- No feedback delivery.
- No journal writes.
- No provider calls.
- No wallet movement.
- No lifecycle truth ownership.
- No unsafe campaign/provider/wallet payload rendering.

## Proposed checkpoints

- Wave 46B — Campaign Recipient Detail / Distribution Backend Prop Bridge
- Wave 46C — Campaign Recipient Detail Context Rendering
- Wave 46D — Campaign Recipient Distribution Context Rendering
- Wave 46E — Campaign Recipient Detail / Distribution Publish / Browser Verification
- Wave 46F — Campaign Recipient Detail / Distribution Context Rendering Closure
