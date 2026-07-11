# Cockpit Wave 29 — Pay Code Explorer Activity Bridge Closure

## Status

Complete.

Wave 29 closes the read-only bridge from Operator Issuance Activity cards on the Cockpit dashboard into the Pay Code Explorer.

## Completed slices

- Wave 29A — Pay Code Explorer Runtime Parity Audit
  - Report: `reports/204-wave-29a-pay-code-explorer-runtime-parity-audit.md`
- Wave 29B — Pay Code Explorer Activity Query Intake
  - Added `activity_code` and `activity_source` query intake for `GET /x/cockpit/pay-codes`.
  - Hydrated `activity_navigation_context` as an operator-safe Inertia prop.
  - Reused `CockpitReadModelQueryData::code` to narrow the read model by Pay Code.
- Wave 29C — Activity Card to Explorer UI Bridge
  - Added an `Open in Explorer` link to operator issuance activity cards.
  - Added a read-only Activity navigation context panel to Pay Code Explorer.
- Wave 29D — Host Publish and Browser Verification
  - Published package-owned Cockpit assets into the host mirror.
  - Verified the published assets are clean.
  - Added a Dusk smoke test for the dashboard activity card bridge and Explorer context page.

## UI impact

Operators can now use an activity card on `/x/cockpit` to open the same Pay Code in `/x/cockpit/pay-codes` with read-only context:

```text
/x/cockpit/pay-codes?activity_code={code}&activity_source=operator_issuance_activity
```

The Explorer renders:

- Activity navigation context
- Pay Code
- Source
- Destination
- Payload policy
- Mutation blocked reason

## Boundary retained

The bridge remains read-only. It does not:

- mutate vouchers
- execute voucher drivers
- call providers
- move money
- write journal entries
- execute x-action actions
- send x-feedback deliveries
- mutate campaign state
- render raw payloads, provider payloads, wallet internals, or recipient secrets

## Verification

Commands executed:

```bash
vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php --filter="operator activity navigation context"
npx vitest run tests/frontend/cockpit/CockpitDashboardHydration.test.ts tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts
php artisan dusk tests/Browser/CockpitPayCodeExplorerActivityBridgeSmokeTest.php
php artisan x-change:doctor --assets --json
vendor/bin/pint --dirty --format agent
```

Results:

- Feature route test: 1 passed, 25 assertions.
- Frontend hydration tests: 2 files passed, 26 tests.
- Dusk bridge smoke: 1 passed, 22 assertions.
- Asset doctor: checked 58, ok 58, stale 0, missing 0, extra 0.
- Pint: passed.

## Next recommended wave

Cockpit Wave 30 — Pay Code Explorer Functional Read Model Parity / Legacy Index Comparison.

Recommended first slice:

```text
Cockpit Wave 30A — Legacy Pay Code Index vs Cockpit Explorer Read Model Parity Audit
```

Purpose:

- compare `/x/pay-codes` functional filters and row facts against `/x/cockpit/pay-codes`
- identify which read-model facts should become operator-visible in Cockpit
- avoid adding new mutation behavior before the Explorer read model reaches functional parity
