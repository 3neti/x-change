# Cockpit Mutation Wave 1E — Quick Generate UI Submit Enablement

Status: UI submit scaffolded; refresh and navigation remain deferred

## Objective

Enable the visible Cockpit Quick Generate submit path after the backend route, existing issuance handoff, and idempotency/replay contract are protected.

## Implemented Boundary

- Added a dedicated Quick Generate submit panel.
- The panel submits only when `quick_generate_read_model.mutation_contract` exposes:
  - `runtime_enabled: true`
  - `route_url`
  - `POST` in `allowed_methods`
- The panel submits to the existing `POST /x/cockpit/quick-generate` route.
- The submitted payload follows the existing `GeneratePayCodeRequest` shape:
  - `cash`
  - `inputs`
  - `feedback`
  - `rider`
  - `metadata.custom.cockpit`
- The panel generates and sends an `Idempotency-Key` header.
- The panel prevents duplicate in-flight submits.
- The panel emits success/error events for later refresh/navigation slices.

## Explicit Non-Goals

This slice does not add:

- a new issuance workflow
- route generation artifacts
- optimistic UI
- Pay Code Explorer redirect
- Voucher Detail redirect
- post-submit read-model refresh
- journal writes
- x-action execution
- x-feedback delivery
- campaign mutation
- direct wallet access
- provider calls outside the existing `GeneratePayCode` path
- direct money movement outside the existing issuance path

## Source Changes

- `resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue`
- `resources/js/cockpit/pages/QuickGenerate.vue`
- `resources/js/cockpit/types.ts`
- `src/Data/Cockpit/CockpitQuickGenerateMutationContractData.php`
- `src/Services/Cockpit/VoucherLifecycleCockpitReadModelProvider.php`

## Test Coverage

- Frontend tests prove the submit panel:
  - submits to the read-model route URL
  - sends `POST`
  - includes an `Idempotency-Key`
  - builds a sanitized `GeneratePayCodeRequest`-compatible payload
  - avoids wallet/provider payload exposure
  - stays disabled without a route URL
  - prevents duplicate in-flight submits
- PHP tests prove the read model exposes the route URL and records the submit gate as passed.
- Architecture tests pin this report and compass update.

## Verification

```bash
npm run test:frontend
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php tests/Unit/Cockpit/CockpitReadModelBaselineTest.php
```

## Next Slice

Cockpit Mutation Wave 1F — Read Model Refresh / Navigation Closure.

