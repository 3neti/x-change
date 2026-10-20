# Instruction cost policy compass

## North star

Every instruction has an understandable economic basis without inventing provider charges or turning ordinary data capture into an artificial per-field fee.

## Invariants

- **Bio fields are free to capture:** name, mobile, email, birthdate, and plain-text address have PHP 0.00 direct provider cost.
- **External services carry cost:** SMS, KYC, geocoding, and evidence storage are costed according to their provider or infrastructure basis.
- **Three values stay separate:** customer price, expected provider cost, and actual provider settlement must never be conflated.
- **Estimates are not cash:** provisional reserves cannot be presented as collected, executed, or settled money.
- **Minor units are canonical:** all persisted and reported amounts use integer minor units.
- **Storage is shared overhead:** Cloud compute, database, cache, object storage, and bandwidth are allocated for internal economics rather than attached to each bio field.
- **Location has two modes:** temporary geocoding and permanently stored geocoding have different cost treatment.
- **Unknown remains unknown:** an unconfigured provider rate stays zero for accounting purposes and is marked as unconfigured; it is not guessed into a settlement.
- **Authoritative evidence wins:** provider invoices, statements, and debit records replace provisional estimates for actual-cost reporting.
- **Package first:** x-commerce owns commercial rate declarations and quote inputs; x-change owns orchestration, settlement evidence, reconciliation, and reporting.

## Reporting rule

Operations Economics may show expected cost, actual provider cost, and variance as separate measures. Only authoritative provider evidence may contribute to actual provider-cost totals.

## Review rule

Revisit provisional storage, Mapbox, and shared-cloud allocations when invoices or measured usage are available. Updating an estimate must not rewrite historical actual settlements.
