# Cockpit Wave 15G — Laravel Dusk Browser Smoke Baseline

Date: 2026-07-11

## Objective

Install Laravel Dusk in the host app and add a narrow browser smoke test for the Cockpit Quick Generate runtime page after Wave 15F copy reconciliation.

## Scope

- Host app only.
- Browser verification only.
- No x-change package production behavior changes.
- No package test-environment changes.
- No journal, action, feedback, provider, wallet, voucher, or campaign mutation changes.

## Implementation

- Added Laravel Dusk `^8.6` to the host app.
- Added root Composer path repositories for the optional read-only package dependencies required by the local `3neti/x-change` path package:
  - `3neti/x-journal`
  - `3neti/x-action`
  - `3neti/x-feedback`
  - `3neti/x-campaign`
- Ran the Dusk installer, which created the host Dusk test harness.
- Replaced the generated welcome-page browser example with `tests/Browser/CockpitQuickGenerateSmokeTest.php`.

## Browser Smoke Assertions

The smoke test logs in with a dedicated local Dusk operator and visits:

```text
/x/cockpit/quick-generate
```

It verifies that the page renders the reconciled runtime copy:

- `Quick Generate Runtime`
- `Submit through existing issuance handoff`
- `Submit will call the existing x-change issuance handoff route.`
- `Existing issuance handoff`
- `Shown after submit`
- `Use the Quick Generate form above`

It also verifies the stale primary operator copy is not visible:

- `No Cockpit mutation route is registered`
- `Quick Generate mutation remains explicitly unauthorized`
- `No voucher generation`
- `No wallet debit or reservation`
- `No journal or feedback side effect`

## Notes

Composer dependency resolution updated the host lockfile while installing Dusk. This included Laravel framework and several transitive dependency updates, plus local path-package references. The local package repositories were needed because Composer does not inherit repositories from a dependency package.

Composer reported security advisory warnings after dependency resolution. That should be handled as a separate dependency-audit task.

## Verification

Commands executed:

```text
composer validate --strict
php artisan x-change:doctor --assets --json
php artisan dusk --filter=CockpitQuickGenerateSmokeTest
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitQuickGenerateReadModelCopyReconciliationTest.php
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/ExampleTest.php
```

Results:

- Composer validation passed.
- Published Cockpit asset drift check passed: checked 56, stale 0, missing 0, extra 0.
- Dusk smoke passed: 1 test, 13 assertions.
- x-change package focused regression passed from the package root: 1 test, 34 assertions.
- Pint passed.
- Host non-database feature smoke passed: 1 test, 1 assertion.

Additional check:

```text
php artisan test --compact tests/Feature/DashboardTest.php
```

Result:

- Failed because the host test environment uses in-memory SQLite without a `users` table for that feature test. This is outside the Dusk browser smoke slice and was not changed here.

## UI Impact

No new UI was added. This checkpoint adds automated browser coverage for the UI state already introduced by Wave 15F.
