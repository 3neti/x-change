# Campaign-to-Quick-Generate Prefill Acceptance — Slice 1

Date: 2026-07-18

## Result

Pass.

The selected local campaign dashboard context now carries into Quick Generate prefill from the simple fixture URL:

`/x/cockpit?campaign_planning_key=plan-local&campaign_execution_id=exec-local`

## What Changed

- Added an acceptance test that:
  - opens the dashboard selected campaign fixture,
  - reads the campaign Quick Generate source link,
  - follows that link into `/x/cockpit/quick-generate`,
  - verifies the Quick Generate route props contain safe campaign prefill context.
- Added a local-fixture metadata fallback in the optional x-campaign adapter so the simple fixture URL includes:
  - template: `ofw-remittance`,
  - amount: `500.00`,
  - currency: `PHP`,
  - recipient: `09173011987`,
  - purpose: `Campaign payout`.

## Boundary Confirmation

This slice remains read-only and prefill-only. It does not add campaign mutation, campaign dispatch, Pay Code generation through campaign, feedback sends, journal writes, action execution, provider calls, wallet behavior, Treasury behavior, public API changes, durable persistence, or money movement.

## Verification

From the host root:

```bash
vendor/bin/pint --dirty --format agent
```

Result: passed.

From `packages/x-change`:

```bash
vendor/bin/pest tests/Feature/Cockpit/CockpitDashboardRealIntegrationReadModelTest.php
```

Result: 4 passed, 104 assertions.

## Next Slice

Campaign-to-Quick-Generate Prefill Acceptance Slice 2 — verify generated activity carries read-only local campaign attribution after Quick Generate submission.
