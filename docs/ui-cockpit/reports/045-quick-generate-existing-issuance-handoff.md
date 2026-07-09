# Cockpit Mutation Wave 1C — Existing Issuance Handoff

Status: Backend handoff scaffolded; UI submit and idempotent replay remain deferred

## Purpose

Wire the Cockpit Quick Generate mutation route to the existing x-change Pay Code issuance action without creating a second issuance workflow.

Wave 1C reuses:

- `GeneratePayCodeRequest` for issuance-compatible validation
- `GeneratePayCode` as the issuance owner
- the existing public API controller route as the unchanged public API boundary

## Route

| Field | Decision |
| --- | --- |
| Method | `POST` |
| Path | `/x/cockpit/quick-generate` |
| Route name | `x-change.cockpit.quick-generate.store` |
| Controller | `CockpitQuickGenerateMutationRouteShellController` |
| Request contract | `GeneratePayCodeRequest` |
| Issuance action | `GeneratePayCode` |
| Response status | `201` |
| Response schema | `x-change.cockpit.quick-generate-existing-issuance-handoff.v1` |

## Behavior

- The Cockpit route validates through `GeneratePayCodeRequest`.
- The Cockpit route calls `GeneratePayCode::handle()`.
- The Cockpit route does not call `GeneratePayCodeController`.
- The existing public API route remains owned by `GeneratePayCodeController`.
- The operator response exposes only safe generated facts:
  - code
  - amount
  - currency
  - redeem link
  - redeem path

## Redaction Boundary

The Cockpit response excludes:

- request payload
- validated payload
- voucher ID
- issuer payload
- wallet data
- debit data
- allocation data
- cost breakdown
- provider payload
- raw payload
- secrets

## Explicit Non-Goals

Wave 1C does not add:

- UI submit enablement
- idempotency persistence
- payload fingerprinting
- replay lookup
- duplicate-submit protection
- Pay Code Explorer refresh behavior
- Voucher Detail navigation handoff
- direct provider calls from Cockpit
- direct wallet access from Cockpit
- direct journal writes from Cockpit
- action execution from Cockpit
- feedback delivery from Cockpit
- campaign mutation behavior

## Safety Decision

The backend route is now capable of issuing through the existing action when called directly.

The Cockpit UI remains disabled until Wave 1D and later slices define:

- idempotency key handling
- replay-safe responses
- conflict behavior
- UI submit state
- post-issuance refresh/navigation

## Next Recommended Slice

```text
Cockpit Mutation Wave 1D — Idempotency and Replay Contract
```

Wave 1D must prevent duplicate operator submits before Cockpit UI submit is enabled.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php tests/Unit/Architecture/CockpitQuickGenerateExistingIssuanceHandoffTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php
```
