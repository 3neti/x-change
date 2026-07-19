# Pay Code Explorer Page Size Control — Slice 2 Closure

Date: 2026-07-20

## Scope

This slice closes the Pay Code Explorer Page Size Control wave by publishing host assets and verifying the browser-local rows-per-page selector.

## Verified

- `php artisan x-change:install --force --no-interaction`
- `php artisan x-change:doctor --assets --no-interaction`
- `npx vitest run tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts`
- `vendor/bin/pest packages/x-change/tests/Unit/Architecture/PayCodeExplorerPageSizeControlTest.php`
- `php artisan dusk tests/Browser/CockpitPayCodeExplorerFilterSmokeTest.php`
- `npm run build`

## Accepted Behavior

- High-volume Explorer results default to `25` rows per page.
- Operators can switch to `10`, `25`, or `50` rows per page.
- Changing row density resets pagination to page 1.
- When the selected density can show all matching rows, pagination controls are hidden.
- Full sanitized read-model totals, link counts, disabled-action counts, search filters, status filters, detail links, distribution links, and read-only boundaries are preserved.

## Boundary

Presentation-only client-side density control. This wave does not change routes, controllers, backend queries, read-model hydration, campaign context propagation, row action links, voucher lifecycle behavior, execution drivers, journal writes, action execution, feedback delivery, campaign mutation, provider calls, wallet behavior, Treasury behavior, public APIs, persistence, artifact generation, or money movement.

## Next Checkpoint

Manual browser inspection of `/x/cockpit/pay-codes`, then continue page-focused Cockpit polish or pick the next real integration wiring wave.
