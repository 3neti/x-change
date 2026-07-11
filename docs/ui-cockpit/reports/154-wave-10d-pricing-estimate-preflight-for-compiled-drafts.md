# Cockpit Wave 10D — Pricing Estimate Preflight for Compiled Drafts

## Status

Implemented.

## Purpose

Expose an operator-safe pricing estimate preflight for compiled Quick Generate drafts before the existing `GeneratePayCode` handoff.

## Behavior

- The route calls `EstimatePayCodeCost` with the compiled draft payload.
- The response includes `preflight.pricing`.
- Pricing preflight is non-blocking.
- If pricing estimation fails, issuance can still continue through the existing handoff.
- Raw pricing charges and raw payloads are not exposed.

## Boundary

This slice does not:

- Block issuance based on price.
- Reserve funds.
- Move money.
- Change provider behavior.
- Persist pricing preflight records.
- Add UI controls.

## Verification

Focused test:

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitQuickGeneratePricingPreflightTest.php
```

## Expected UI Effect

None until the frontend renders `preflight.pricing`.

## Next Recommended Checkpoint

Cockpit Wave 10E — Balance/Funding Preflight using `BuildBalanceOverview`.
