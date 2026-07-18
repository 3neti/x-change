# Real Campaign Read-Only Wiring Wave — Slice 1 — Package Presence Summary

Date: 2026-07-18

## Scope

Let Cockpit distinguish between:

- x-campaign is not installed/configured; and
- x-campaign is installed, but no campaign is selected in the current dashboard context.

## Changes

- Updated the optional campaign Cockpit adapter so a resolved `CampaignCockpitWorkspace` no longer degrades to unavailable only because no planning key is present.
- Added a package-presence read model with:
  - `status: available`;
  - `authorized: true`;
  - `context_status: no-campaign-selected`;
  - `payloads: campaign-cockpit-package-presence-only`;
  - mutation, Pay Code issuance, feedback, journal, wallet, provider, and money-movement flags all disabled.
- Preserved the existing selected-campaign `summary()` path when a planning key exists.
- Preserved fail-closed unavailable behavior when service resolution fails or a selected-campaign summary throws.

## Boundary

This is read-model wiring only. It does not register campaign routes, add controllers, mutate campaign plans, issue Pay Codes through campaign, dispatch feedback, write journal entries, execute actions, call providers, change wallet behavior, change Treasury behavior, alter public APIs, or move money.

## Verification

- `vendor/bin/pest tests/Unit/Cockpit/CockpitReadModelBaselineTest.php`
  - Result: 21 tests passed, 334 assertions.
- `vendor/bin/pint --dirty --format agent packages/x-change/src/Services/Cockpit/OptionalCockpitIntegrationReadModels.php packages/x-change/tests/Unit/Cockpit/CockpitReadModelBaselineTest.php`
  - Result: passed.
