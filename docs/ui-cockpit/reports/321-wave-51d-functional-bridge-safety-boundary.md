# Cockpit Wave 51D — Functional Bridge Safety Boundary

## Status

Completed.

## Boundary

The campaign/template Quick Generate bridge is a preparation bridge only.

Allowed:

- adapt campaign context into a Cockpit issuance draft;
- compile that draft into an existing `GeneratePayCodeRequest`-compatible payload;
- preserve campaign metadata for attribution and navigation.

Blocked:

- direct Pay Code issuance;
- direct calls to `GeneratePayCode`;
- campaign mutation;
- campaign batch execution;
- distribution dispatch;
- feedback delivery;
- journal writes;
- action execution;
- provider calls;
- wallet movement;
- lifecycle truth ownership;
- unsafe payload exposure.

## Protected source files

- `DefaultCockpitCampaignIssuanceDraftAdapter`
- `DefaultCockpitIssuanceDraftCompiler`

## Decision

The next wave may decide whether to wire this bridge into a visible or runtime Cockpit flow.

That next wave must still keep `GeneratePayCode` as the issuance owner and must not create a parallel campaign issuance runtime.

## Next slice

`Cockpit Wave 51E — Campaign Template Quick Generate Functional Bridge Closure`
