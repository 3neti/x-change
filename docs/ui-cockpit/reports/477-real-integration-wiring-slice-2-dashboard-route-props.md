# Real Integration Wiring Wave — Slice 2 — Dashboard Route Props

Date: 2026-07-18

## Scope

Verify that `/x/cockpit` receives real read-only Connected Services props from installed integration packages.

## Changes

- Added route-level Inertia coverage for dashboard Connected Services.
- Confirmed dashboard props now expose:
  - x-journal as read-only evidence;
  - x-action as presentation-only action summaries;
  - x-feedback as communication-state summaries.
- Confirmed voucher and execution facts remain fallback/not-wired when no Pay Code is selected.

## Boundary

This is route-prop verification only. It does not write journal entries, execute x-action actions, send x-feedback deliveries, dispatch campaigns, mutate vouchers, execute drivers, generate artifacts, call providers, change wallet behavior, change Treasury behavior, alter public APIs, or move money.

## Verification

- `vendor/bin/pest tests/Feature/Cockpit/CockpitDashboardRealIntegrationReadModelTest.php`
  - Result: 1 test passed, 23 assertions.
- `vendor/bin/pint --dirty --format agent packages/x-change/tests/Feature/Cockpit/CockpitDashboardRealIntegrationReadModelTest.php`
  - Result: passed.
