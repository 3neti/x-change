# Commercial Pricing Compass

## Purpose

Keep Pay Code issuance pricing consistent across the authenticated cockpit, public auto-generate flow, batch issuance, APIs, and future widgets.

## Boundary

x-change owns the route and domain entry point because it understands voucher instructions. It normalizes those instructions and delegates commercial calculation to x-commerce. x-commerce owns the active catalog, pricing versions, deterministic quote references, and immutable snapshots.

## Runtime flow

```text
Cockpit, auto-generate, batch, or API
        -> x-change issuance route
        -> Pay Code instruction normalization
        -> x-commerce quote service
        -> quote and pricing snapshot
        -> checkout/funding order
        -> issuance
```

## Invariants

- The amount shown before payment and the amount required by funding use the same quote snapshot.
- A newly published price applies to new quotes immediately.
- Existing funded or accepted orders retain their original pricing snapshot.
- No host application calculates Pay Code prices independently.
- Quote APIs estimate only; x-change controls acceptance, payment, and issuance.

## Operations

Payment monitoring is presented at `/x/operations`. `/x/checkout` remains a compatibility route. Pricing management is adjacent to Payment Operations and must not expose raw provider credentials or private payment evidence.
