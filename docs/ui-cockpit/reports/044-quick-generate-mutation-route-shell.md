# Cockpit Mutation Wave 1B — Quick Generate Mutation Route Shell

Status: Route shell scaffolded; mutation runtime remains disabled

## Purpose

Register the planned Quick Generate Cockpit mutation route name without enabling Pay Code issuance.

Wave 1B turns the Wave 1A route contract into an authenticated route shell. The route exists so later slices can wire the existing issuance path deliberately, under tests, without inventing a second issuance workflow.

## Route Shell

| Field | Decision |
| --- | --- |
| Method | `POST` |
| Path | `/x/cockpit/quick-generate` |
| Route name | `x-change.cockpit.quick-generate.store` |
| Controller | `CockpitQuickGenerateMutationRouteShellController` |
| Runtime enabled | `false` |
| Response status | `409` |
| Response schema | `x-change.cockpit.quick-generate-mutation-route-shell.v1` |

## Boundary Decisions

- The route is authenticated by the existing Cockpit web middleware.
- The route returns an operator-safe disabled-runtime JSON response.
- The route records the intended `GeneratePayCodeRequest` compatibility boundary.
- The route records the intended `GeneratePayCodeController` / `GeneratePayCode` handoff boundary.
- The route does not execute request validation.
- The route does not call `GeneratePayCodeRequest`.
- The route does not call `GeneratePayCodeController`.
- The route does not call `GeneratePayCode`.

## Safety Gates

| Gate | Status | Decision |
| --- | --- | --- |
| Route Contract Defined | passed | Reserved route name is now registered as a shell. |
| Request Adapter Defined | planned | Adapter remains deferred to a later slice. |
| Issuance Owner Confirmed | passed | `GeneratePayCode` remains the issuance owner. |
| Idempotency Required | planned | Idempotency remains required before submit can be enabled. |
| Operator Response Redacted | passed | Shell response excludes request, validated, provider, wallet, and raw payloads. |
| Runtime Disabled | blocked | Shell does not issue Pay Codes. |

## Explicit Non-Goals

Wave 1B does not add:

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

## Next Recommended Slice

```text
Cockpit Mutation Wave 1C — Existing Issuance Handoff
```

Wave 1C should add the first controlled handoff to the existing issuance path with fake issuance tests and idempotency/redaction boundaries. UI submit enablement should remain deferred until the handoff, idempotency, response, and refresh behavior are protected.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php tests/Unit/Architecture/CockpitQuickGenerateMutationRouteShellTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php
```
