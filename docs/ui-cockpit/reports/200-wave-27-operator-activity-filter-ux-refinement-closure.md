# Cockpit Wave 27 — Operator Activity Filter UX Refinement / Multi-Select Decision

## Status

Complete.

## Objective

Refine the read-only Operator Issuance Activity filter UX after Wave 26 browser/manual acceptance.

## Implemented Slices

### Wave 27A — Multi-Select Decision

Decision recorded in:

```text
reports/199-wave-27a-operator-activity-filter-multiselect-decision.md
```

Result:

- keep visible filter controls single-select for now;
- defer multi-select until activity volume or operator triage needs justify it;
- retain backend list normalization as future-ready infrastructure.

### Wave 27B — Compact Active Filter Summary

`CockpitOperatorIssuanceActivityPanel` now renders a compact summary of active filters.

Example:

```text
Filters: search “money changer” · status issued · handoff recorded
```

When durable activity is not wired, the panel renders:

```text
Filter summary unavailable until durable activity storage is wired.
```

### Wave 27C — Clear-Per-Filter Links

`CockpitOperatorIssuanceActivityPanel` now renders read-only clear links for each active filter category:

- `Clear search`;
- `Clear status`;
- `Clear handoff`.

Each link uses `GET /x/cockpit` with adjusted query parameters.

No POST route, saved filter, runtime mutation, or handoff mutation is introduced.

### Wave 27D — Publish / Browser / Compass Closure

Published host Cockpit assets with:

```bash
php artisan x-change:install --force
```

Verified published assets with:

```bash
php artisan x-change:doctor --assets --json
```

Asset drift result:

```text
checked 58, ok 58, stale 0, missing 0, extra 0
```

Verified the browser smoke surface with:

```bash
php artisan dusk tests/Browser/CockpitDashboardActivityFilterSmokeTest.php
```

Result:

```text
Dusk activity filter smoke: 1 passed, 23 assertions
```

## UI Impact

Operators should now see:

- the existing Operator Issuance Activity filter bar;
- a compact active-filter summary below the result summary;
- clear-per-filter links for search, status, and handoff filters;
- unchanged read-only safety copy.

## Explicit Boundaries

Wave 27 does not add:

- visible multi-select controls;
- saved filter presets;
- filter persistence;
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

## Verification

Commands run:

```bash
vendor/bin/pest tests/Unit/Architecture/CockpitWave27aOperatorActivityFilterMultiselectDecisionTest.php
npx vitest run tests/frontend/cockpit/CockpitDashboardHydration.test.ts
php artisan x-change:install --force
php artisan x-change:doctor --assets --json
php artisan dusk tests/Browser/CockpitDashboardActivityFilterSmokeTest.php
vendor/bin/pint --dirty --format agent
```

Results:

```text
Wave 27A architecture test: 1 passed, 9 assertions
Dashboard frontend hydration: 18 passed
Asset drift doctor: checked 58, ok 58, stale 0, missing 0, extra 0
Dusk activity filter smoke: 1 passed, 23 assertions
Pint: passed
```

## Next Recommended Wave

Cockpit Wave 28 — Operator Activity Filter Browser Acceptance / Next Runtime Decision.
