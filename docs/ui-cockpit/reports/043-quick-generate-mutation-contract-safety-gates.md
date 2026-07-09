# Cockpit Mutation Wave 1A — Quick Generate Mutation Contract and Safety Gates

Status: Contract scaffolded; mutation runtime remains disabled

Superseded by Wave 1B for route registration only. Runtime remains disabled.

## Purpose

Scaffold the first approved mutation-capable Cockpit contract without implementing the mutation route or invoking issuance.

Wave 1A converts the prior Quick Generate mutation plan into a read-model contract that future implementation slices can follow.

## Contract Summary

| Field | Decision |
| --- | --- |
| Schema | `x-change.cockpit.quick-generate-mutation.v1` |
| Planned route | `x-change.cockpit.quick-generate.store` |
| Runtime enabled | `false` |
| Allowed route methods now | `GET` |
| Request adapter | `GeneratePayCodeRequest-compatible-adapter` |
| Issuance owner | `GeneratePayCode` |
| Idempotency | required before submit can be enabled |
| Operator response | operator-safe redacted result |

## Safety Gates

| Gate | Status | Decision |
| --- | --- | --- |
| Route Contract Defined | planned | POST route name is reserved; route is not registered in Wave 1A. |
| Request Adapter Defined | planned | Adapter must remain compatible with `GeneratePayCodeRequest`. |
| Issuance Owner Confirmed | passed | `GeneratePayCode` remains the issuance owner. |
| Idempotency Required | planned | Idempotency key is required before UI submit can be enabled. |
| Operator Response Redacted | planned | Response must expose operator-safe generated facts only. |
| Runtime Disabled | blocked | No mutation route or submit behavior is enabled in Wave 1A. |

## Read Model Shape

Wave 1A adds `quick_generate_read_model.mutation_contract` with:

- `schema`
- `status`
- `authorization`
- `route`
- `request_adapter`
- `issuance_owner`
- `idempotency`
- `response_contract`
- `runtime_enabled`
- `gates`
- `allowed_methods`
- `redactions`

This shape is informational and operator-visible only after read-model hydration. It is not a route, request, job, command, provider adapter, wallet adapter, or issuance service.

## Explicit Non-Goals

Wave 1A does not add:

- Cockpit `POST`, `PUT`, `PATCH`, or `DELETE` routes
- `GeneratePayCode` invocation
- `GeneratePayCodeController` invocation
- `GeneratePayCodeRequest` validation execution
- submitted payload persistence
- idempotency key generation or persistence
- payload fingerprinting
- replay lookup
- voucher issuance
- voucher execution
- wallet lookup, reservation, debit, or transfer
- provider calls
- journal writes
- action execution
- feedback delivery
- campaign issuance behavior
- money movement

## Next Authorized Slice

The next slice was later authorized and scaffolded as a route shell only.

Completed next slice:

```text
Cockpit Mutation Wave 1B — Quick Generate Mutation Route Shell
```

Wave 1B adds only a route shell with authorization/validation boundaries. It does not enable the UI submit button, run request validation, call issuance, call providers, access wallets, write journal entries, execute actions, send feedback, or move money.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/CockpitReadModelBaselineTest.php tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php tests/Unit/Architecture/CockpitQuickGenerateMutationContractSafetyGatesTest.php
```
