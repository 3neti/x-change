# Real Campaign Read-Only Wiring Wave — Slice 2 — Dashboard Route Props

Date: 2026-07-18

## Scope

Verify that `/x/cockpit` exposes installed x-campaign package availability without requiring selected campaign context.

## Changes

- Added route-level Inertia coverage for dashboard campaign read-model props.
- Confirmed `campaign_read_model` reports:
  - `status: available`;
  - `source: x-campaign`;
  - `context_status: no-campaign-selected`;
  - `payloads: campaign-cockpit-package-presence-only`.
- Confirmed campaign mutations, Pay Code issuance through campaign, feedback, journal, and money-movement flags remain disabled.
- Confirmed no raw/provider/wallet/campaign mutation payloads are exposed.

## Boundary

This is route-prop verification only. It does not register campaign routes, add controllers, mutate campaign plans, issue Pay Codes through campaign, dispatch feedback, write journal entries, execute actions, call providers, change wallet behavior, change Treasury behavior, alter public APIs, or move money.

## Verification

- `vendor/bin/pest tests/Feature/Cockpit/CockpitDashboardRealIntegrationReadModelTest.php`
  - Result: 2 tests passed, 45 assertions.
- `vendor/bin/pint --dirty --format agent packages/x-change/tests/Feature/Cockpit/CockpitDashboardRealIntegrationReadModelTest.php`
  - Result: passed.
