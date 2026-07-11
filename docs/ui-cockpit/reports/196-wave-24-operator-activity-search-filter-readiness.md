# Cockpit Wave 24 — Operator Activity Search / Filter Runtime Readiness

## Status

Complete.

## Objective

Prepare Cockpit operator activity for read-only search and filtering without adding mutation controls or changing issuance/runtime side effects.

## Implemented Slices

### Wave 24A — Search / Filter DTO Baseline

Added:

```text
CockpitOperatorIssuanceActivitySearchFilterData
```

The DTO normalizes:

- free-text search;
- activity status filters;
- handoff status filters.

Blank values are removed and duplicate filter values are collapsed.

### Wave 24B — Repository Filtering Baseline

Extended existing repository query behavior through:

```text
CockpitReadModelQueryData::operatorActivityFilters
```

Covered repositories:

- `InMemoryCockpitOperatorIssuanceActivityRepository`
- `DatabaseCockpitOperatorIssuanceActivityRepository`

Filtering remains read-only and supports:

- text match against operator-safe activity facts;
- activity status filtering;
- journal/action/feedback handoff status filtering;
- existing operator, correlation, and Pay Code constraints.

### Wave 24C — Read Model Filter Metadata

Extended:

```text
CockpitOperatorIssuanceActivityReadModelData::search_filters
```

The read model now exposes operator-safe filter metadata:

- active search term;
- active status filters;
- active handoff status filters;
- available statuses from returned records;
- available handoff statuses from returned records;
- read-only safety flags.

The TypeScript Cockpit read model type was updated to match the Inertia payload shape.

## Explicit Boundaries

Wave 24 does not add:

- runtime configuration mutation UI;
- journal/action/feedback handoff enablement controls;
- retry, resend, or rerun controls;
- provider calls;
- wallet mutation;
- voucher execution mutation;
- journal/action/feedback write expansion;
- campaign mutation;
- raw payload display.

## UI Impact

No visible UI change is expected in Wave 24.

The backend/read-model shape is now ready for a future read-only UI slice to render search fields, filter chips, and empty states.

## Verification

Commands run:

```bash
vendor/bin/pest tests/Unit/Cockpit/CockpitOperatorIssuanceActivitySearchFilterDataTest.php
vendor/bin/pest tests/Unit/Cockpit/CockpitOperatorIssuanceActivityRepositoryContractTest.php tests/Feature/Cockpit/CockpitOperatorIssuanceActivityDatabaseRepositoryTest.php
vendor/bin/pest tests/Unit/Cockpit/CockpitOperatorIssuanceActivityReadModelTest.php tests/Feature/Cockpit/CockpitOperatorIssuanceActivityDurableReadModelAdapterTest.php
npm run test:frontend
```

Results:

```text
Search/filter DTO: 2 passed, 9 assertions
Repository filtering: 13 passed, 43 assertions
Read-model filter metadata: 8 passed, 81 assertions
Frontend suite: 76 files passed, 482 tests passed
```

## Next Recommended Wave

Cockpit Wave 25 — Operator Activity Search / Filter UI Presentation.

Recommended scope:

- render read-only search/filter controls in the Operator Issuance Activity panel;
- submit filter state through existing read-model query plumbing;
- show active filters and safe empty states;
- keep all row actions read-only;
- keep mutation controls absent.
