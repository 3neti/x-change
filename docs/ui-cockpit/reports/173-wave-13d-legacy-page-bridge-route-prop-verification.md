# Cockpit Wave 13D — Legacy Page Bridge Route Prop Verification

## Status

Implemented.

## Purpose

Protect the route-prop and frontend wiring that makes Cockpit bridge metadata visible on legacy pages.

## Verified Surfaces

| Legacy page | Bridge destination | Rule |
|---|---|---|
| `/x/pay-codes/create` | `/x/cockpit/quick-generate` | Legacy advanced generation remains owner. |
| `/x/pay-codes` | `/x/cockpit/pay-codes` | Legacy list/search remains owner. |
| `/x/balances` | `/x/cockpit` | Legacy balance authority remains owner. |

## Boundary

The callout says the legacy page remains the functional owner. Cockpit is an operator shell bridge and does not replace the legacy page behavior.

This slice does not add mutations, route redirects, wallet movement, provider calls, journal writes, action execution, feedback delivery, or campaign operations.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitPayCodeCreateBridgeMarkerTest.php tests/Feature/Cockpit/CockpitExplorerBalancesBridgeMarkerTest.php tests/Unit/Architecture/CockpitWave13dLegacyPageBridgeRoutePropVerificationTest.php
```

## Next Recommended Checkpoint

Cockpit Wave 13E — Quick Generate Operator-Focused Presentation Closure.
