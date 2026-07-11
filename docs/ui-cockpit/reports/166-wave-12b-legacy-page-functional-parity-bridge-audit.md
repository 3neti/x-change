# Cockpit Wave 12B — Legacy Page Functional Parity Bridge Audit

## Status

Implemented.

## Purpose

Record the current functional ownership of legacy x-change pages before bridging their capabilities into Cockpit.

## Current Legacy Pages

| Route | Controller | Current role |
|---|---|---|
| `/x/pay-codes/create` | `PayCodeCreatePageController` | Full legacy Pay Code generation form, cost estimation, balance overview context |
| `/x/pay-codes` | `PayCodeIndexPageController` | Legacy Pay Code list/search surface |
| `/x/balances` | `BalancePageController` | Balance authority and reconciliation surface |

## Bridge Rule

Cockpit should bridge, not replace, these pages.

## Implications

- Cockpit Quick Generate is now the template-first fast path.
- `/x/pay-codes/create` remains the advanced/full form path.
- `/x/pay-codes` remains the broader list surface until Cockpit explorer reaches functional parity.
- `/x/balances` remains the detailed balance authority surface until Cockpit exposes equivalent read models.

## Next Recommended Checkpoint

Cockpit Wave 12C — Pay Code Create Page Template/Campaign Draft Bridge Marker.
