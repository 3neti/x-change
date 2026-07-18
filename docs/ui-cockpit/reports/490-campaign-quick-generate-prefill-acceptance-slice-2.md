# Campaign-to-Quick-Generate Prefill Acceptance — Slice 2

Date: 2026-07-18

## Result

Pass.

Quick Generate now has focused acceptance coverage proving that a selected local campaign fixture submission records safe campaign attribution in durable operator activity.

## What Changed

- Added a combined runtime test for the local fixture campaign path.
- Verified the Quick Generate mutation response includes safe campaign attribution:
  - planning key: `plan-local`,
  - execution id: `exec-local`,
  - campaign id: `campaign-local`,
  - audience id: `audience-local`,
  - source: `campaign_cockpit`,
  - generated Pay Code,
  - template,
  - amount,
  - currency,
  - recipient reference,
  - purpose.
- Verified durable operator issuance activity metadata carries the same safe attribution.
- Verified post-issuance navigation exposes read-only campaign Explorer and Dashboard links.
- Verified unsafe campaign, recipient, provider, wallet, and delivery payloads are not exposed.

## Boundary Confirmation

This slice does not add campaign mutation, campaign dispatch, Pay Code generation through campaign, feedback sends, journal writes beyond the existing configured handoff runtime, action execution, provider calls, wallet behavior, Treasury behavior, public API changes, or money movement.

## Verification

From the host root:

```bash
vendor/bin/pint --dirty --format agent
```

Result: passed.

From `packages/x-change`:

```bash
vendor/bin/pest tests/Feature/Cockpit/CockpitQuickGenerateCombinedRuntimeTest.php tests/Feature/Cockpit/CockpitDashboardRealIntegrationReadModelTest.php
```

Result: 7 passed, 185 assertions.

## Next Slice

Campaign-to-Quick-Generate Prefill Acceptance Slice 3 — host publish / asset drift / frontend verification / build closure.
