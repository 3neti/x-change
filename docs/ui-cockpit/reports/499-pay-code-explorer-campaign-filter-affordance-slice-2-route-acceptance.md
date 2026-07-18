# Pay Code Explorer Campaign Filter Affordance — Slice 2 — Route Acceptance

Date: 2026-07-18

## Scope

Verify that campaign context and Explorer search/status filters can coexist on the backend route.

## Result

- Added route-level acceptance that `/x/cockpit/pay-codes` preserves campaign planning key, execution ID, campaign ID, audience ID, recipient ID, and source while applying read-only search/status filters.
- Verified search/status filters hydrate into the sanitized list read model.
- Verified campaign context remains read-only and mutation/provider/wallet/raw payload surfaces remain absent.

## Verification

Commands:

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/pest tests/Feature/Cockpit/CockpitReadOnlyRoutesTest.php
```

Results:

- Pint passed
- 47 tests passed
- 808 assertions passed

## Boundary

No campaign route, campaign controller, campaign mutation, campaign dispatch, Pay Code issuance through campaign, journal write, action execution, feedback delivery, provider call, wallet behavior, Treasury behavior, public API behavior, or money movement changed.
