# Cockpit Wave 30 — Pay Code Explorer Functional Read Model Parity Closure

## Status

Complete.

## Completed slices

- Wave 30A — Legacy Pay Code Index vs Cockpit Explorer Read Model Parity Audit
  - Report: `reports/206-wave-30a-pay-code-explorer-functional-parity-audit.md`
- Wave 30B — Pay Code Explorer Filter / Summary Read Model Contract
  - Report: `reports/207-wave-30b-pay-code-explorer-filter-summary-read-model-contract.md`
- Wave 30C — Pay Code Explorer Provider Filtering and Stats Parity
  - Report: `reports/208-wave-30c-pay-code-explorer-provider-filtering-stats-parity.md`
- Wave 30D — Pay Code Explorer Controller Query Intake
  - Report: `reports/209-wave-30d-pay-code-explorer-controller-query-intake.md`
- Wave 30E — Pay Code Explorer Filter UI Presentation
  - Report: `reports/210-wave-30e-pay-code-explorer-filter-ui-presentation.md`
- Wave 30F — Pay Code Explorer Filter Browser / Publish Verification
  - Report: `reports/211-wave-30f-pay-code-explorer-filter-browser-publish-verification.md`

## As-built capability

Cockpit Pay Code Explorer now has read-only functional parity with the legacy Pay Code index for:

- search query intake
- status query intake
- legacy-compatible status inference
- sanitized filtered rows
- sanitized summary stats
- status filter options
- active filter summary
- clear filters link
- host-published asset drift protection

The Cockpit route is:

```text
GET /x/cockpit/pay-codes?search={term}&status={status}
```

## Verification

Passed:

- `vendor/bin/pest tests/Unit/Architecture/CockpitWave30aPayCodeExplorerFunctionalParityAuditTest.php`
- `vendor/bin/pest tests/Unit/Cockpit/CockpitPayCodeExplorerReadModelContractTest.php`
- `vendor/bin/pest tests/Unit/Cockpit/CockpitPayCodeExplorerProviderParityTest.php`
- `vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php --filter="search and status query filters"`
- `npx vitest run tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts`
- `php artisan x-change:doctor --assets --json`
- `vendor/bin/pint --dirty --format agent`

Browser smoke:

- Added `tests/Browser/CockpitPayCodeExplorerFilterSmokeTest.php`.
- Execution was blocked in this run because escalated browser approval timed out twice and the sandboxed fallback could not reach ChromeDriver on `localhost:9515`.

## Boundary retained

Wave 30 did not:

- replace `/x/pay-codes`
- change legacy Pay Code index behavior
- mutate vouchers
- execute voucher drivers
- change claim UX
- call providers
- reserve, debit, or move money
- write journal entries
- execute x-action actions
- send x-feedback deliveries
- mutate campaign state
- expose raw payloads, provider payloads, wallet internals, account numbers, OTPs, recipient secrets, or funding sources

## Next recommended wave

Cockpit Wave 31 — Pay Code Explorer Detail Navigation / Row Action Runtime Parity.

Recommended first slice:

```text
Cockpit Wave 31A — Pay Code Explorer Row Action Parity Audit
```

Purpose:

- compare legacy index row actions with Cockpit Explorer row actions
- decide which row actions should become read-only navigation links
- keep claim approval, notification, provider, and money movement actions blocked unless explicitly authorized
