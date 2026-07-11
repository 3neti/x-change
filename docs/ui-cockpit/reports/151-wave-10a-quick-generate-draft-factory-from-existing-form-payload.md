# Cockpit Wave 10A — Quick Generate Draft Factory from Existing Form Payload

## Status

Implemented.

## Purpose

Convert the existing Cockpit Quick Generate form payload into the Wave 9 functional issuance draft model without changing the current issuance route behavior.

This is the first runtime compiler adoption checkpoint after the Wave 9 template/campaign issuance foundation.

## Added

- `CockpitQuickGenerateDraftFactoryContract`
- `DefaultCockpitQuickGenerateDraftFactory`
- Runtime binding in `XChangeServiceProvider`
- Unit characterization for existing Quick Generate payload mapping
- Runtime binding test
- Draft → validator → compiler → `GeneratePayCodeRequest` compatibility test

## Characterized Payload Shape

The factory accepts the current Quick Generate payload shape:

- `cash.amount`
- `cash.currency`
- `cash.validation`
- `inputs.fields`
- `count`
- `feedback.email`
- `feedback.mobile`
- `feedback.webhook`
- `rider.message`
- `rider.url`
- `rider.splash`
- `rider.splash_timeout`
- `metadata.custom.cockpit.template_key`
- `metadata.custom.cockpit.source`
- `_meta.idempotency_key`
- `_meta.correlation_id`

## Defaults Preserved

- Missing currency becomes `PHP`.
- Missing count becomes `1`.
- Missing template key becomes `money-changer`.
- Missing validation becomes an empty array.
- Missing input fields becomes an empty array.
- Empty scalar strings normalize to `null`.
- Explicit idempotency/correlation arguments override payload `_meta`.

## Boundary

This slice does not:

- Change the Quick Generate HTTP route.
- Call `GeneratePayCode`.
- Issue vouchers.
- Reserve funds.
- Move money.
- Invoke providers.
- Write journal entries.
- Execute actions.
- Send feedback.
- Mutate campaigns.
- Persist raw payloads.
- Add UI changes.

## Verification

Focused tests:

```bash
php -d memory_limit=1G vendor/bin/pest \
  tests/Unit/Cockpit/DefaultCockpitQuickGenerateDraftFactoryTest.php \
  tests/Feature/Cockpit/CockpitQuickGenerateDraftFactoryRuntimeBindingTest.php \
  tests/Feature/Cockpit/CockpitQuickGenerateDraftFactoryScenarioTest.php
```

## Expected UI Effect

None. This is a backend/package factory seam only.

## Next Recommended Checkpoint

Cockpit Wave 10B — Quick Generate Route Uses Draft Validator and Compiler.
