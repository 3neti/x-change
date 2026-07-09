# Cockpit Mutation Wave 2H — Activity UI Rendering Boundary

Status: Implemented

## Scope

This slice renders operator issuance activity presentations on the Cockpit dashboard.

The UI consumes:

```text
operator_issuance_activity_read_model.presentations
```

through:

```text
CockpitOperatorIssuanceActivityPanel
```

This is read-only dashboard rendering. It does not create or invoke any runtime handoff.

## Implemented

- Added `CockpitOperatorIssuanceActivityPanel`.
- Added Cockpit TypeScript contracts for operator issuance activity read models and presentation DTOs.
- Rendered the panel from the package Cockpit dashboard page.
- Added frontend coverage for:
  - rendering sanitized presentation facts;
  - displaying handoff statuses;
  - preserving route-adapter prop forwarding;
  - rendering an unavailable/empty state;
  - excluding unsafe payloads and mutation affordances.

## Boundary

This slice intentionally performs:

- no mutation controls;
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

## Tests

Red baseline:

```bash
npm run test:frontend -- CockpitDashboardHydration.test.ts
```

Result:

```text
3 failed, 13 passed
```

Expected failures:

- missing `Operator Issuance Activity` panel;
- missing presentation summary rendering;
- missing dashboard route-adapter rendering.

Focused green result:

```bash
npm run test:frontend -- CockpitDashboardHydration.test.ts
```

Result:

```text
16 passed
```

Full frontend regression:

```bash
npm run test:frontend
```

Result:

```text
74 passed, 476 tests
```

Focused PHP boundary regression:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php tests/Unit/Architecture/CockpitOperatorIssuanceActivityUiRenderingBoundaryTest.php tests/Unit/Architecture/CockpitOperatorIssuanceActivityReadModelPresentationAdoptionTest.php tests/Unit/Architecture/SettlementOsIntegrationReadinessReportTest.php
```

Result:

```text
42 passed, 655 assertions
```

Full package PHP regression:

```bash
php -d memory_limit=1G vendor/bin/pest
```

Result:

```text
1120 passed, 5 skipped, 6741 assertions
```

Build check:

```bash
npm run build
```

Result:

```text
Not available in the x-change package; npm reported "Missing script: build".
```

## Next Recommended Slice

Cockpit Mutation Wave 2I — Published Asset Sync / Drift Guard Validation

Wave 2I should validate that package-owned Cockpit UI changes can be safely published to the host app through the existing install/drift workflow. It should not edit host mirror files directly.
