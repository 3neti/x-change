# Cockpit Wave 10E — Balance/Funding Preflight using BuildBalanceOverview

## Status

Implemented.

## Purpose

Expose an operator-safe funding preflight snapshot for compiled Quick Generate drafts using the existing `BuildBalanceOverview` service.

## Behavior

- The route calls `BuildBalanceOverview` with stale sync disabled.
- The response includes `preflight.funding`.
- Funding preflight is non-blocking.
- If the balance overview is unavailable, issuance can still continue through the existing handoff.
- Raw balance lists and provider wallet identifiers are not exposed.

## Boundary

This slice does not:

- Reserve funds.
- Block issuance based on balance.
- Sync provider balances.
- Move money outside the existing `GeneratePayCode` path.
- Persist funding preflight records.
- Add UI controls.

## Verification

Focused test:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitQuickGenerateFundingPreflightTest.php
```

## Expected UI Effect

None until the frontend renders `preflight.funding`.

## Next Recommended Checkpoint

Cockpit Wave 10F — Campaign Draft Runtime Intake Boundary.
