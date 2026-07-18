# Real Campaign Read-Only Wiring Wave — Slice 3 — Dashboard Panel Copy

Date: 2026-07-18

## Scope

Update the Dashboard campaign panel so installed x-campaign package presence is visually distinct from a missing integration.

## Changes

- When x-campaign is installed but no campaign is selected, the campaign panel now shows:
  - `Campaign package connected`;
  - `No campaign selected`;
  - `Read-only campaign summaries`;
  - `Ready when a campaign is selected`.
- Selected-campaign summaries still show campaign name, recipient count, planning key, execution id, and read-only navigation links.
- Unavailable x-campaign still renders as not connected.
- Published the package-owned Cockpit asset into the host app.

## Boundary

This is presentation-only. It does not register campaign routes, add controllers, mutate campaign plans, issue Pay Codes through campaign, dispatch feedback, write journal entries, execute actions, call providers, change wallet behavior, change Treasury behavior, alter public APIs, or move money.

## Verification

- `npm run test:frontend -- tests/frontend/cockpit/CockpitDashboardHydration.test.ts`
  - Result: 1 test file passed, 31 tests passed.
- `php artisan x-change:install --force --no-interaction`
  - Result: passed.
- `php artisan x-change:doctor --assets --no-interaction`
  - Result: published Cockpit assets match package source.

