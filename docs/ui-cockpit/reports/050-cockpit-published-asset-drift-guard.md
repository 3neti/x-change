# Cockpit Published Asset Drift Guard

## Status

Implemented on 2026-07-10.

## Purpose

Protect the package-owned Cockpit source of truth from accidental edits to host-published runtime mirrors.

Package source remains authoritative:

```text
packages/x-change/resources/js/cockpit
packages/x-change/resources/js/pages/x-change/cockpit
```

Host-published mirrors remain runtime copies only:

```text
resources/js/cockpit
resources/js/pages/x-change/cockpit
```

## Implemented Guard

- Added a package service that compares package Cockpit source files against host-published mirrors.
- Added `php artisan x-change:doctor --assets --json` to report:
  - synchronized files
  - stale files
  - missing published files
  - extra published files
- Added install-time warning header stamping for Cockpit host-published files after `php artisan x-change:install`.
- Header stripping is built into the comparator so generated warnings do not produce false drift.
- Warning header stamping only updates published files that already match package source semantically; stale host edits are reported and skipped rather than overwritten by the header step.

## Current Local Result

Command:

```bash
php artisan x-change:doctor --assets --json
```

Result:

```text
published cockpit assets: passed
checked: 54
stale: 0
missing: 0
extra: 0
```

## Operator Policy

When Cockpit UI edits are needed:

1. Edit package source under `packages/x-change/resources/js/...`.
2. Run the package frontend/Pest tests relevant to the change.
3. Publish into the host app with `php artisan x-change:install --force`.
4. Run `php artisan x-change:doctor --assets --json`.
5. Treat stale/missing/extra files as a stop condition before manual UI validation.

## Boundary

This slice does not add Cockpit features, routes, API behavior, mutation behavior, provider calls, journal writes, action execution, feedback delivery, wallet access, or money movement.

It only adds drift detection and published-copy warnings.

## Tests

Focused tests:

```bash
cd packages/x-change && php -d memory_limit=1G vendor/bin/pest tests/Unit/Services/PublishedAssetDriftDetectorTest.php tests/Feature/Console/DoctorXChangeCommandTest.php
```

Result:

```text
7 passed, 28 assertions
```
