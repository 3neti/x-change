# Cockpit Wave 55D — Distribution Workspace Manual Copy Adoption

## Status

Completed on 2026-07-12.

## Scope

Wave 55D adopts the reusable manual copy control on Distribution Workspace for the beneficiary Pay Code URL.

## Implemented

- Imported `CockpitManualCopyButton` into Distribution Workspace.
- Added the copy control inside the read-only beneficiary URL panel.
- The control copies the absolute beneficiary URL when present, falling back to the relative path if needed.
- Frontend tests prove the copy action writes to the browser clipboard and does not call `fetch`.

## Boundaries

Distribution Workspace copy remains browser-local and does not:

- call backend endpoints
- persist copy events
- send feedback
- dispatch campaigns
- write journal entries
- execute actions
- call providers
- mutate vouchers
- move money

## Verification

- `npm run test:frontend -- tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts`
- `php -d memory_limit=1G vendor/bin/pest tests/Unit/Architecture/CockpitWave55dDistributionWorkspaceManualCopyAdoptionTest.php`

## Next

Cockpit Wave 55E — Manual Copy Publish / Drift Verification Closure.
