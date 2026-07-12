# Cockpit Wave 52A — Campaign Template Quick Generate Runtime Adoption Audit

## Status

Completed.

## Scope

Audit how the Wave 51 campaign/template Quick Generate bridge reaches the live Cockpit Quick Generate mutation runtime.

## Findings

- `CockpitQuickGenerateMutationRouteShellController` already uses the existing runtime handoff path:
  - `GeneratePayCodeRequest`
  - `CockpitQuickGenerateDraftFactoryContract`
  - `CockpitIssuanceDraftValidatorContract`
  - `CockpitIssuanceDraftCompilerContract`
  - `GeneratePayCode`
- Campaign context is accepted through the request payload under `metadata.campaign`.
- The route response already exposes operator-safe campaign attribution and campaign-aware post-issuance navigation.
- The route does not call a campaign package, mutate campaign state, perform bulk issuance, or create a second issuance runtime.

## Adoption Boundary

Wave 52 should adopt the campaign/template bridge through the existing Quick Generate payload path.

It should not inject the campaign draft adapter directly into the mutation route because the adapter is a read-model/source-link preparation seam, while the mutation route must remain `GeneratePayCodeRequest` compatible.

## Runtime Gap

The frontend campaign-prefill submit path currently carries recipient mobile through `feedback.mobile`, but it does not mirror that recipient into `cash.validation.mobile` or mark `inputs.fields` with `mobile`.

That gap is relevant because Wave 51 proved the compiler bridge should produce a `GeneratePayCodeRequest`-compatible payload with mobile validation semantics.

## Decision Pending

Wave 52B should record the adoption decision.

Wave 52C should harden the runtime payload shape so campaign-prefilled Quick Generate submits include the same validation/mobile shape as the compiler bridge.

## Non-Goals

- No campaign mutation.
- No campaign repository write.
- No bulk issuance.
- No provider call from Cockpit.
- No wallet movement from Cockpit.
- No journal/action/feedback side effect from Cockpit.
