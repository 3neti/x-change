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

## Agreement Economics

The active Offering remains the only price authority. A separate immutable
Component Economics artifact binds each catalog item to its complete allocation
schedule without restating or overriding the item's price. It references the
exact Offering version and snapshot hash, conserves every centavo, and fails
closed when a priced component, external recipient designation, or authority is
missing.

```text
Active Commercial Offering price
    -> versioned Component Economics schedule
    -> active Commercial Recipient Designation
    -> accepted Commercial Quote snapshot
    -> atomic, itemized Treasury recognition
       -> Royalty Payable
       -> Provider Cost Payable
       -> Partner Commission Payable
       -> Tax Payable (only under an explicit governed tax policy)
       -> Product or Commercial Revenue
       -> Institution-Owned Funds
```

The default commissioning artifact explicitly assigns every priced component
to the 3neti service-provider recipient until a later governed agreement
supersedes it. No unlisted or implicit recipient is permitted. Recognition is
automatic at issuance; external cash settlement remains a separate,
evidence-controlled operation. A TIN by itself never invents a tax rate or tax
liability.

## Billable Event evidence

Every priced quote line accepted at issuance produces one durable Billable
Event inside the same transaction as its Commercial Sale and Treasury
allocation. The event records the governed component, event type, recognition
policy, quantity, unit price, total, currency, source event, and immutable
payload hash. It never restates a catalog price as a second authority.

The recognition policy is resolved from an explicit governed registry. Every
new Billable Event freezes the policy version, canonical snapshot, and SHA-256
hash. The policy must expressly authorize the component's Billable Event.
Unknown, malformed, mismatched, or deferred policies fail before any Treasury
posting. The initial baseline recognizes on `commercial_sale.accepted`;
deferred recognition requires a future Unearned Instruction Charge treatment
and cannot silently fall through to immediate revenue or payable recognition.

```text
Accepted governed quote line
    -> received Billable Event
    -> Commercial charge and itemized allocations post
    -> Billable Event becomes posted
    -> append-only recognition journal evidence

Compensating Commercial Sale reversal
    -> reverse allocations and charge exactly once
    -> Billable Event becomes reversed under the same reason reference
    -> append-only reversal journal evidence
```

The event reference is deterministic per Commercial Sale and component. An
identical replay returns the original record. A replay with changed economics
fails closed. Legacy sales without Component Economics remain readable and are
not retroactively given invented recognition evidence.
