# Cockpit Mutation Wave 2I — Published Asset Sync / Drift Guard Validation

Status: Implemented

## Scope

This checkpoint validates that package-owned Cockpit UI changes are detected when host-published mirrors are stale.

It does not publish assets and does not edit host mirror files directly.

## Command

Run from the host app root:

```bash
php artisan x-change:doctor --assets --json
```

## Result

The drift guard correctly reported that host-published Cockpit mirrors no longer match the package source after Wave 2H.

Summary:

```text
checked: 55
ok: 52
stale: 2
missing: 1
extra: 0
```

Detected drift:

```text
missing components/CockpitOperatorIssuanceActivityPanel.vue
stale pages/Dashboard.vue
stale types.ts
```

This is the expected state until the host app runs the package install/publish workflow.

## Boundary

- no host mirror files were staged;
- no host mirror files were committed;
- no manual host mirror edits were made;
- no package source-of-truth files were copied by hand;
- no routes, controllers, APIs, persistence, journal writes, action execution, feedback delivery, provider calls, wallet access, voucher execution changes, or money movement were added.

## Recommended Host Validation Flow

When the user wants to test the new package UI in the host app:

```bash
php artisan x-change:install --force
php artisan x-change:doctor --assets --json
```

Expected after publishing:

```text
published cockpit assets: passed
stale: 0
missing: 0
extra: 0
```

## Tests

Focused documentation/readiness gate:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitPublishedAssetSyncDriftValidationTest.php tests/Unit/Architecture/SettlementOsIntegrationReadinessReportTest.php
```

Result:

```text
3 passed, 43 assertions
```

Formatter:

```bash
../../vendor/bin/pint --dirty --format agent
```

Result:

```text
passed
```

## Next Recommended Slice

Cockpit Mutation Wave 2J — Activity UI Host Publish Verification

Wave 2J may run the install/publish workflow and verify the host app sees the new package-owned activity panel. It should still avoid manual host mirror edits.
