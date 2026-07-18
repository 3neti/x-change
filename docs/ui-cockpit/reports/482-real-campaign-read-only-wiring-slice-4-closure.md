# Real Campaign Read-Only Wiring Wave — Slice 4 — Closure

Date: 2026-07-18

## Scope

Close the Real Campaign Read-Only Wiring wave with backend, route-prop, frontend, published asset, and host build verification.

## Result

Cockpit can now represent x-campaign as a real installed read-only integration even when no campaign is selected.

Expected `/x/cockpit` copy:

- `Campaign package connected`
- `No campaign selected`
- `Read-only campaign summaries`
- `Ready when a campaign is selected`

Selected campaign contexts still hydrate through `CampaignCockpitWorkspace::summary`. No selected campaign means no campaign plan details are loaded.

## Boundary

This wave did not register campaign routes, add controllers, mutate campaign plans, issue Pay Codes through campaign, dispatch feedback, write journal entries, execute actions, call providers, change wallet behavior, change Treasury behavior, alter public APIs, or move money.

## Verification

- `vendor/bin/pest tests/Unit/Cockpit/CockpitReadModelBaselineTest.php tests/Feature/Cockpit/CockpitDashboardRealIntegrationReadModelTest.php`
  - Result: 23 tests passed, 379 assertions.
- `npm run test:frontend -- tests/frontend/cockpit/CockpitDashboardHydration.test.ts tests/frontend/cockpit/CockpitDashboardFoundation.test.ts`
  - Result: 2 test files passed, 36 tests passed.
- `php artisan x-change:doctor --assets --no-interaction`
  - Result: published Cockpit assets match package source.
- `npm run build`
  - Result: passed.
  - Note: Vite emitted the existing non-blocking third-party Rolldown `#__PURE__` annotation warnings from `reka-ui/@vueuse`; the build completed successfully.

