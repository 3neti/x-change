# Cockpit Wave 12E — Functional Parity Bridge Closure / Host Publish Handoff

## Status

Implemented.

## Purpose

Close Wave 12 by recording the functional bridge between Cockpit Quick Generate and the existing legacy x-change pages:

- `/x/pay-codes/create`
- `/x/pay-codes`
- `/x/balances`

The goal is functional parity orientation, not UI replacement.

## Completed Slices

| Slice | Result |
|---|---|
| Cockpit Wave 12A | Quick Generate stale baseline copy was removed or demoted so the page no longer contradicts the working issuance handoff after a successful generation. |
| Cockpit Wave 12B | Legacy page functional ownership was audited and recorded. |
| Cockpit Wave 12C | `/x/pay-codes/create` now exposes an operator-safe `cockpit_bridge` prop to Cockpit Quick Generate. |
| Cockpit Wave 12D | `/x/pay-codes` and `/x/balances` now expose operator-safe `cockpit_bridge` props to Cockpit read-model destinations. |

## Current Functional Position

Quick Generate can issue a Pay Code through the existing `GeneratePayCode` handoff and render operator-safe runtime facts:

- pricing preflight
- funding preflight
- draft runtime
- activity runtime

The legacy pages remain canonical for their current functional scope:

- advanced Pay Code generation remains owned by `/x/pay-codes/create`
- Pay Code listing/search remains owned by `/x/pay-codes`
- balance authority/reconciliation remains owned by `/x/balances`

Cockpit bridges to these surfaces. It does not replace them yet.

## Host Publish Handoff

Because Wave 12 changed package Cockpit UI source, the host-published mirrors may be stale until the package assets are republished.

Run from the host app root:

```bash
php artisan x-change:install --force
npm run dev
```

The package assets under `packages/x-change/resources/js/cockpit` remain the source of truth.

## Boundaries

This closure does not add:

- new money movement
- new wallet mutation
- new provider calls
- campaign mutation
- journal writes
- action execution
- feedback delivery
- replacement behavior for `/x/pay-codes/create`, `/x/pay-codes`, or `/x/balances`

## Verification

```bash
npm run test:frontend -- CockpitQuickGenerateFoundation.test.ts
php -d memory_limit=1G vendor/bin/pest tests/Feature/Cockpit/CockpitPayCodeCreateBridgeMarkerTest.php tests/Feature/Cockpit/CockpitExplorerBalancesBridgeMarkerTest.php tests/Unit/Architecture/CockpitWave12bLegacyPageFunctionalParityBridgeAuditTest.php tests/Unit/Architecture/CockpitWave12eFunctionalParityBridgeClosureTest.php
```

## Next Recommended Wave

Cockpit Wave 13 — Legacy Page Bridge UI Presentation.

Recommended first slice:

```text
Cockpit Wave 13A — Render cockpit_bridge callouts on /x/pay-codes/create, /x/pay-codes, and /x/balances
```

That wave should make the bridge visible on the legacy pages without changing their behavior.
