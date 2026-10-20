# Operations Economics Dashboard Plan

## Status

Implemented as a read-only x-change package surface using
`3neti/report-registry` v1.1.3.

## Boundary

The `CommercialOperationsEconomicsReadModel` reads existing checkout, funding,
commercial sale, allocation, provider-cost, reversal, and claim records. It
does not write ledger records, execute bridge transfers, or enable Product
revenue movement.

Six versioned report drivers expose summary, waterfall, Product revenue,
positions, movements, and exceptions. The same report payload feeds the
operations and authenticated cockpit dashboards.

All monetary values remain integer minor units. Planned, shadow, executed,
reversed, and exception states are presented separately.
