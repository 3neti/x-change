# Commercial Pricing Authority

## Invariant

The catalog inside the currently active governed Commercial Offering is the
sole source of effective Voucher Instruction pricing.

Package configuration supplies an installation baseline. It is not a mutable
runtime price list. The `instruction_items` table and lifecycle price-list API
are projections of the active Offering and must carry its provenance.

## Authority flow

```text
Structured Commercial Workspace draft
    -> deterministic Commercial Offering YAML
    -> canonical manifest hash + Offering snapshot hash
    -> immutable database Offering version
    -> maker submission
    -> independent checker publication
    -> explicit activation
    -> active runtime pricing authority
```

The YAML is the reviewable source artifact. The active database Offering is the
executable authority. A Commercial Sale is the immutable historical record of
the price and allocation accepted for one economic event.

## Estimate-to-issuance lock

```text
Voucher Instructions
    -> estimate from active Offering
       (Offering reference + version + snapshot hash)
    -> client accepts the estimate identity
    -> issuance re-resolves pricing
    -> identity mismatch: stop, refresh, and retry
    -> issue Voucher inside the financial transaction
    -> sale posting re-quotes inside that transaction
    -> Offering or total mismatch: roll back the transaction
    -> persist Commercial Sale with the accepted Offering snapshot
```

This prevents silent repricing both before issuance and during the issuance
transaction. A changed Offering is never treated as an equivalent price merely
because the final total happens to match.

## Versioning and evidence

Each Offering version retains:

- Offering reference and positive version;
- profile and effective time;
- full catalog, waterfall, attribution, and legal snapshot;
- Offering snapshot hash;
- deterministic manifest schema, YAML, and manifest hash;
- maker, checker, authorization, publication, and activation evidence; and
- origin and package/commissioning provenance.

An activated version is not edited. Any price or policy change creates the next
version. Activation history remains append-only; prior Commercial Sales remain
bound to their original Offering snapshot.

## Compatibility surfaces

The following may remain for backward compatibility but are not pricing
authorities:

- `x-change.pricing.components` and the legacy `PricingService`;
- `x-change.pricelist` configuration;
- mutable `instruction_items.price` values; and
- UI-local calculations.

Runtime service bindings, lifecycle projections, public price-list reads, Pay
Code estimation, issuance, and Commercial Sale posting must all resolve the
active governed Offering.

## Scope of this foundation

This foundation consolidates prices and provenance. Component recipient
allocations, automatic payable/revenue consummation, Institution-Owned Funds,
and tax withholding remain subsequent governed schemas. Those schemas must
reference the same immutable Offering version; they must not introduce another
price list.
