# Cockpit Slice 24 — Quick Generate Mutation Handoff Boundary Plan

## Scope

Cockpit Slice 24 adds a read-only mutation handoff boundary plan for Quick Generate.

The slice documents and exposes the future handoff path from Cockpit to the existing x-change issuance owner. It does not register mutation routes, call issuance actions, submit request payloads, generate vouchers, call providers, move money, write journal entries, run actions, or send feedback.

## Handoff Facts

The baseline handoff facts are:

- `existing-issuance-owner-identified`
- `generate-pay-code-action-handoff`
- `generate-pay-code-controller-handoff`
- `preconditions-green`
- `side-effect-boundary-confirmed`
- `operator-response-contract-ready`

Only `existing-issuance-owner-identified` is marked as passed because the correct architectural owner is known: future Cockpit mutation behavior must hand off to existing x-change issuance paths instead of inventing generation behavior in the operator shell.

All action, controller, precondition, side-effect, and response-contract handoff checks remain blocked.

## Boundary

Mutation handoff remains a read-only boundary plan in Slice 24.

No Cockpit mutation route calls GeneratePayCode, GeneratePayCodeController, providers, wallets, journal, action, or feedback in Slice 24.

The slice does not introduce:

- POST, PUT, PATCH, or DELETE Cockpit routes
- `GeneratePayCode` invocation
- `GeneratePayCodeController` handoff
- request submission
- validated payload persistence
- mutation payload persistence
- issued voucher payload exposure
- generated Pay Code payload exposure
- wallet lookup, reservation, debit, or transfer
- provider calls
- journal writes
- action runs
- feedback delivery
- operator mutation response contracts

## Preconditions Before Future Mutation Wiring

A future mutation slice must not begin until the following are explicitly green:

- operator authorization
- pricing calculation
- funding source selection
- funding sufficiency and reservation strategy
- idempotency key and replay policy
- request validation
- submitted-payload redaction
- mutation response contract
- side-effect ownership and rollback strategy

## Redaction

The mutation handoff plan read model exposes only plan status and diagnostic reasons.

The following payload classes remain excluded:

- `request_payload`
- `validated_payload`
- `mutation_payload`
- `issued_voucher`
- `generated_pay_code`
- `provider_payload`
- `wallet`
- `journal_payload`
- `action_payload`
- `feedback_payload`
- `side_effect_result`
- `raw_payload`

## Implementation Notes

The read model additions are:

- `CockpitQuickGenerateMutationHandoffPlanData`
- `CockpitQuickGenerateMutationHandoffPlanStepData`
- `quick_generate_read_model.mutation_handoff_plan`
- `CockpitQuickGenerateMutationHandoffPlanPanel`

The existing disabled generate action remains disabled. The new mutation handoff plan explains the future boundary that must be satisfied before a mutation route can be considered.

## Verification

The Slice 24 tests protect:

- default not-wired mutation handoff plan shape
- hydrated Quick Generate mutation handoff plan facts
- absence of Cockpit mutation routes
- absence of mutation payload, issued voucher, generated Pay Code, and side-effect result exposure
- frontend rendering without forms or side effects
