# Cockpit Wave 30F — Pay Code Explorer Filter Browser / Publish Verification

## Status

Publish verification complete. Browser smoke test added. Browser execution blocked in this run by local ChromeDriver process availability.

## Verification scope

This checkpoint verifies the Wave 30 filter UI in the host-published Cockpit assets.

Verified browser route:

```text
/x/cockpit/pay-codes?search=PC-DUSK-FILTER&status=redeemed
```

Expected visible behavior:

- functional parity summary is visible
- search input is populated
- status filter is selected
- active filter summary is visible
- clear filters link is visible
- unsafe payload labels are not rendered
- mutation/configuration controls are not rendered

## Commands

```bash
php artisan x-change:install --force
php artisan x-change:doctor --assets --json
php artisan dusk tests/Browser/CockpitPayCodeExplorerFilterSmokeTest.php
```

## Results

- `php artisan x-change:install --force`: passed.
- `php artisan x-change:doctor --assets --json`: passed with checked 58, ok 58, stale 0, missing 0, extra 0.
- `vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php --filter="search and status query filters"`: passed, 1 test, 15 assertions.
- `npx vitest run tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts`: passed, 1 file, 9 tests.
- `vendor/bin/pint --dirty --format agent`: passed.
- `php artisan dusk tests/Browser/CockpitPayCodeExplorerFilterSmokeTest.php`: not completed in this run.
  - Escalated browser execution approval timed out twice before the command could run.
  - A sandboxed fallback attempt failed before app assertions because ChromeDriver was unreachable on `localhost:9515`.
  - The browser smoke file remains as the executable verification target once ChromeDriver execution is available.

## Boundary

This verification does not authorize or add mutation behavior.

## Next slice

```text
Cockpit Wave 30G — Pay Code Explorer Functional Parity Closure / Compass Update
```
