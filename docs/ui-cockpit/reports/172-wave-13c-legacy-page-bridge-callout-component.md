# Cockpit Wave 13C — Legacy Page Bridge Callout Component

## Status

Implemented.

## Purpose

Make the Wave 12 `cockpit_bridge` metadata visible on legacy x-change pages without changing their behavior.

## Behavior

- Added a reusable `CockpitBridgeCallout` Vue component.
- `/x/pay-codes/create` can show a bridge to Cockpit Quick Generate.
- `/x/pay-codes` can show a bridge to Cockpit Pay Code Explorer.
- `/x/balances` can show a bridge to the Cockpit dashboard.
- The callout explicitly says the legacy page remains the functional owner.

## Boundary

This slice does not replace legacy pages, change routes, submit new mutations, move money, call providers, write journals, execute actions, send feedback, or enable campaign mutation.

## Verification

```bash
npm run test:frontend -- CockpitLegacyBridgeCallout.test.ts
```

## Next Recommended Checkpoint

Cockpit Wave 13D — Legacy Page Bridge Route Prop Verification.
