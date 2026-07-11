# Cockpit Wave 35A — Campaign Context Quick Generate Adoption Audit

## Mission

Define how campaign/program context may prepare a Quick Generate draft without making Cockpit responsible for campaign mutation, bulk issuance, delivery, or lifecycle truth.

## Current State

The x-change package already has:

- `CockpitCampaignIssuanceDraftAdapterContract`;
- `DefaultCockpitCampaignIssuanceDraftAdapter`;
- `CockpitIssuanceDraftData::campaign`;
- compiler support that preserves campaign context in `metadata.campaign`;
- campaign navigation context for Pay Code Explorer.

Quick Generate currently does not accept campaign query context on its GET route and does not prefill its form from campaign context.

## Functional Target

Quick Generate should be able to receive operator-safe campaign query context and use it to prepare/prefill a draft through the existing campaign issuance draft adapter.

## Authorized Scope

- Accept safe campaign query parameters on the Quick Generate GET route.
- Attach campaign context to the Quick Generate read model.
- Prefill the Quick Generate form from the read model where safe.
- Submit the existing Quick Generate mutation route with `metadata.campaign` preserved by the existing draft/compiler path.

## Boundaries

Wave 35 must not:

- mutate campaigns;
- create campaign recipients;
- approve imports;
- perform bulk issuance;
- dispatch campaign feedback;
- call campaign package mutation APIs;
- replace x-campaign as program owner;
- create campaign routes;
- expose unsafe campaign payloads;
- bypass the existing `GeneratePayCode` handoff.

## Proposed Slices

- Wave 35B — Campaign Context Quick Generate Read Model Contract.
- Wave 35C — Quick Generate Campaign Query Intake / Provider Hydration.
- Wave 35D — Quick Generate Campaign Prefill UI Presentation.
- Wave 35E — Campaign Context Quick Generate Browser / Publish Verification.
- Wave 35F — Campaign Context Quick Generate Adoption Closure.

## Expected UI Result

No visible UI change in this audit slice.

## Next Slice

Cockpit Wave 35B — Campaign Context Quick Generate Read Model Contract.
