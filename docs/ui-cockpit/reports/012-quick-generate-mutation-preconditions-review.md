# Cockpit Slice 25 — Quick Generate Mutation Preconditions Review

## Scope

Cockpit Slice 25 adds a read-only mutation preconditions review for Quick Generate.

The slice reviews whether the existing Quick Generate readiness gates are sufficient to propose mutation-route scaffolding. The result is explicit: the mutation preconditions remain blocked and the recommended path remains read-only.

## Review Items

The baseline review items are:

- `authorization-ready`
- `pricing-ready`
- `funding-ready`
- `idempotency-ready`
- `validation-redaction-ready`
- `handoff-ready`
- `operator-response-ready`

All items are blocked. No item graduates to mutation readiness in Slice 25.

## Recommendation

The Slice 25 recommendation is:

```text
remain-read-only
```

Mutation preconditions remain blocked in Slice 25.

## Boundary

No Cockpit mutation route, mutation approval, request validation execution, voucher issuance, provider call, wallet access, journal write, action run, or feedback delivery is introduced in Slice 25.

The slice does not introduce:

- mutation routes
- mutation approval
- request validation execution
- submitted payload persistence
- validated payload persistence
- precondition payload persistence
- `GeneratePayCode` invocation
- `GeneratePayCodeController` handoff
- voucher issuance
- wallet lookup, reservation, debit, or transfer
- provider calls
- journal writes
- action runs
- feedback delivery
- mutation response contracts

## Redaction

The mutation preconditions review exposes only review status, recommendation, and diagnostic reasons.

The following payload classes remain excluded:

- `request_payload`
- `validated_payload`
- `precondition_payload`
- `mutation_approval`
- `mutation_route`
- `issued_voucher`
- `generated_pay_code`
- `provider_payload`
- `wallet`
- `side_effect_result`
- `raw_payload`

## Implementation Notes

The read model additions are:

- `CockpitQuickGenerateMutationPreconditionsReviewData`
- `CockpitQuickGenerateMutationPreconditionsReviewItemData`
- `quick_generate_read_model.mutation_preconditions_review`
- `CockpitQuickGenerateMutationPreconditionsReviewPanel`

The disabled generate action remains disabled. Slice 25 records that the next implementation step should not be a mutation route unless explicit human approval changes the boundary.

## Verification

The Slice 25 tests protect:

- default not-wired mutation preconditions review shape
- hydrated Quick Generate mutation preconditions review facts
- absence of Cockpit mutation routes
- absence of precondition payload, mutation approval, mutation route, issued voucher, generated Pay Code, and side-effect result exposure
- frontend rendering without forms or side effects
