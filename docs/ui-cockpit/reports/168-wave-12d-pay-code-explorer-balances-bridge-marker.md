# Cockpit Wave 12D — Pay Code Explorer / Balances Bridge Marker

## Status

Implemented.

## Purpose

Expose bridge markers from legacy `/x/pay-codes` and `/x/balances` to their Cockpit read-model destinations.

## Behavior

- `/x/pay-codes` receives `cockpit_bridge` pointing to `/x/cockpit/pay-codes`.
- `/x/balances` receives `cockpit_bridge` pointing to `/x/cockpit`.
- Legacy pages remain owners of their current functional surfaces.
- Cockpit does not replace either page.
- Only bridge metadata is exposed.

## Boundary

This slice does not change list/search behavior or balance calculation behavior.

## Verification

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitExplorerBalancesBridgeMarkerTest.php
```

## Next Recommended Checkpoint

Cockpit Wave 12E — Functional Parity Bridge Closure / Host Publish Handoff.
