# Cockpit Mutation Wave 2G — Activity Read Model Presentation Adoption

Status: Implemented

## Scope

This slice adopts the operator issuance activity presentation DTOs into the existing read-model surface.

The dashboard route now exposes:

```text
operator_issuance_activity_read_model
```

with:

```text
items
presentations
redactions
empty_state
```

The `presentations` collection is a read-only dashboard prop. It is suitable for future UI rendering, but it does not make Cockpit the lifecycle truth owner.

## Implemented

- Added `presentations` to `CockpitOperatorIssuanceActivityReadModelData`.
- Hydrated `operator_issuance_activity_read_model` from `CockpitReadOnlyPageProps::toDashboardArray()`.
- Kept the default provider response safe and not-wired.
- Added read-model, route-prop, and architecture documentation tests.

## Boundary

This slice intentionally performs:

- no handoff invocation;
- no journal writes;
- no action execution;
- no feedback delivery;
- no persistence;
- no migrations;
- no queues;
- no provider calls;
- no wallet access;
- no voucher execution changes;
- no raw payload exposure;
- no money movement.

## Files Changed

- `src/Data/Cockpit/CockpitOperatorIssuanceActivityReadModelData.php`
- `src/Support/Cockpit/CockpitReadOnlyPageProps.php`
- `tests/Unit/Cockpit/CockpitOperatorIssuanceActivityReadModelTest.php`
- `tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php`
- `tests/Unit/Architecture/CockpitOperatorIssuanceActivityReadModelPresentationAdoptionTest.php`

## Tests

Red baseline:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/CockpitOperatorIssuanceActivityReadModelTest.php tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php tests/Unit/Architecture/CockpitOperatorIssuanceActivityReadModelPresentationAdoptionTest.php
```

Result:

```text
4 failed, 39 passed
```

Expected failures:

- missing `presentations` read-model field;
- missing dashboard `operator_issuance_activity_read_model` prop;
- missing 2G documentation/compass entries.

Focused green result:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Cockpit/CockpitOperatorIssuanceActivityReadModelTest.php tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php tests/Unit/Architecture/CockpitOperatorIssuanceActivityReadModelPresentationAdoptionTest.php
```

Result:

```text
43 passed, 649 assertions
```

Boundary/readiness regression:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitOperatorIssuanceActivityReadModelPresentationAdoptionTest.php tests/Unit/Architecture/CockpitOperatorIssuanceActivityPresentationClosureTest.php tests/Unit/Architecture/CockpitOperatorIssuanceActivityFeedbackHandoffBoundaryTest.php tests/Unit/Architecture/CockpitOperatorIssuanceActivityActionHandoffBoundaryTest.php tests/Unit/Architecture/CockpitOperatorIssuanceActivityJournalHandoffBoundaryTest.php tests/Unit/Architecture/SettlementOsIntegrationReadinessReportTest.php
```

Result:

```text
7 passed, 101 assertions
```

Full package regression:

```bash
php -d memory_limit=1G vendor/bin/pest
```

Result:

```text
1119 passed, 5 skipped, 6726 assertions
```

## Next Recommended Slice

Cockpit Mutation Wave 2H — Activity UI Rendering Boundary

Wave 2H may render `operator_issuance_activity_read_model.presentations` in the Cockpit dashboard. It must remain read-only and must not invoke journal, action, or feedback handoffs.
