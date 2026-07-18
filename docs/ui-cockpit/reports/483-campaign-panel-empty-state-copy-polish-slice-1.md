# Campaign Panel Empty-State Copy Polish — Slice 1

Date: 2026-07-18

## Scope

Replace remaining implementation-facing campaign empty-state copy with operator-facing copy after x-campaign package presence is detected.

## Changes

- Replaced `No campaign panels authorized for display.` with `Select a campaign to see workspace panels.` when x-campaign is connected but no campaign is selected.
- Replaced `No campaign actions authorized for display.` with `Select a campaign to see available campaign actions.` when x-campaign is connected but no campaign is selected.
- Replaced the dedicated workspace placeholder with operator-facing copy explaining that a dedicated campaign workspace is not enabled yet.
- Published package-owned Cockpit assets into the host app.

## Boundary

This is presentation-only. It does not register campaign routes, add controllers, mutate campaign plans, issue Pay Codes through campaign, dispatch feedback, write journal entries, execute actions, call providers, change wallet behavior, change Treasury behavior, alter public APIs, or move money.

## Verification

- `npm run test:frontend -- tests/frontend/cockpit/CockpitDashboardHydration.test.ts`
  - Result: 1 test file passed, 31 tests passed.
- `php artisan x-change:install --force --no-interaction`
  - Result: passed.
- `php artisan x-change:doctor --assets --no-interaction`
  - Result: published Cockpit assets match package source.

