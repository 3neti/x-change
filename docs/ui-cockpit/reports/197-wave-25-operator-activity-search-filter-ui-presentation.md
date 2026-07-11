# Cockpit Wave 25 — Operator Activity Search / Filter UI Presentation

## Status

Complete.

## Objective

Expose the Wave 24 operator activity search/filter readiness as read-only Cockpit UI controls.

## Implemented Slices

### Wave 25A — Dashboard Query Intake

The Cockpit dashboard route now accepts read-only operator activity filter query parameters:

```text
activity_search
activity_status
activity_handoff_status
```

These are normalized into `CockpitOperatorIssuanceActivitySearchFilterData` and passed through `CockpitReadModelQueryData::operatorActivityFilters`.

### Wave 25B — Operator Activity Filter Controls

`CockpitOperatorIssuanceActivityPanel` now renders:

- a search input;
- an activity status selector;
- a handoff status selector;
- an apply button;
- a clear link;
- active filter chips;
- read-only safety copy.

The form uses `GET /x/cockpit`.

### Wave 25C — Host Publish Verification

Published Cockpit assets were synchronized into the host mirror with:

```bash
php artisan x-change:install --force
php artisan x-change:doctor --assets --json
```

Asset drift result:

```text
checked 58, ok 58, stale 0, missing 0, extra 0
```

### Wave 25D — Browser Smoke

Added Dusk smoke test:

```text
tests/Browser/CockpitDashboardActivityFilterSmokeTest.php
```

It verifies:

- `/x/cockpit` accepts filter query params;
- the filter controls render;
- the selected filter state is visible;
- the filtered operator activity card renders;
- mutation/configuration/provider/raw payload controls remain absent.

## Explicit Boundaries

Wave 25 does not add:

- POST/PUT/PATCH/DELETE filter routes;
- runtime configuration mutation UI;
- handoff enablement toggles;
- retry, resend, rerun, or execute controls;
- provider calls;
- wallet mutation;
- voucher execution mutation;
- journal/action/feedback write expansion;
- campaign mutation;
- raw payload display.

## UI Impact

Operators should now see a read-only filter bar in the `Operator Issuance Activity` dashboard panel.

Expected visible controls:

- `SEARCH ACTIVITY`
- `STATUS`
- `HANDOFF`
- `Apply filters`
- `Clear`
- active filter chips when query parameters are present.

## Verification

Commands run:

```bash
vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php --filter="operator activity search filter"
npx vitest run tests/frontend/cockpit/CockpitDashboardHydration.test.ts
php artisan x-change:install --force
php artisan dusk tests/Browser/CockpitDashboardActivityFilterSmokeTest.php
php artisan x-change:doctor --assets --json
../../vendor/bin/pint --dirty --format agent
```

Results:

```text
Dashboard query intake: 1 passed, 10 assertions
Dashboard frontend hydration: 17 passed
Dusk activity filter smoke: 1 passed, 23 assertions
Asset drift doctor: checked 58, ok 58, stale 0, missing 0, extra 0
Pint: passed
```

## Next Recommended Wave

Cockpit Wave 26 — Operator Activity Filter Manual Browser Acceptance / Query UX Hardening.

Recommended scope:

- manually confirm the new filter UI with local data;
- decide whether multi-select filters are needed;
- decide whether query state should be represented in the URL only or also in a compact summary header;
- keep all controls read-only and side-effect free.
