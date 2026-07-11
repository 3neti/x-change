# Cockpit Wave 12C — Pay Code Create Page Template/Campaign Draft Bridge Marker

## Status

Implemented.

## Purpose

Expose an explicit bridge marker from the legacy `/x/pay-codes/create` advanced form to the Cockpit Quick Generate template/draft runtime.

## Behavior

- `/x/pay-codes/create` now receives `cockpit_bridge`.
- The bridge points to `/x/cockpit/quick-generate`.
- The legacy page remains the owner of the advanced/full form.
- Cockpit does not replace the legacy create page.
- Campaign mutation remains disabled.
- Only bridge metadata is exposed.

## Boundary

This slice does not change the legacy form behavior or add a new UI component to the legacy page yet.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitPayCodeCreateBridgeMarkerTest.php
```

## Next Recommended Checkpoint

Cockpit Wave 12D — Pay Code Explorer / Balances Bridge Marker.
