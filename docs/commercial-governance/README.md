# Commercial Governance

x-change commissions an institution with usable prices without pretending that a human maker or checker approved them. The package ships an immutable baseline Commercial Offering for each governed profile. Commissioning records the package version, manifest reference, complete snapshot, and hash, then activates it through the `commissioning_manifest` authority.

The default mode is `bootstrap_immutable`:

1. installation activates package-defined pricing;
2. Pay Code issuance is available immediately after the other commissioning gates pass;
3. price changes remain locked until different named people hold maker and checker authority;
4. the System Principal never acts as either person;
5. every later version is drafted, submitted, approved, published, and explicitly activated;
6. existing Pay Codes and Commercial Sales retain their original snapshot and hash.

Set `XCHANGE_COMMERCIAL_GOVERNANCE_MODE=maker_checker_from_start` only when institutional policy forbids baseline activation. In that mode commissioning persists the baseline evidence but does not activate it, so governed issuance remains unavailable until the institution completes its own approval and activation.

## State model

```text
Package baseline
    -> provisioned with package and manifest provenance
    -> activated by commissioning authority
    -> issuance available, changes locked

Named maker + different named checker
    -> draft
    -> submit
    -> approve and publish
    -> explicit activation
    -> new issuance uses new snapshot
```

Publication and activation are deliberately different. Publication says an independently reviewed version is eligible. Activation is the controlled instant when new work begins resolving that version. A published but inactive version cannot affect pricing.

## Invariants

- A baseline hash conflict stops installation; x-change never overwrites it.
- One current activation exists per profile.
- Activation validates the stored snapshot hash.
- The System Principal cannot receive maker or checker authority.
- One person cannot hold both maker and checker authority.
- The checker cannot approve the maker's own draft.
- Ordinary Account holders cannot view Commercial Controls.
- Commissioning exposes role counts and readiness, not operator identities.
- Governance events are written to x-journal with stable idempotency keys.
- Commercial allocations are system-side positions, never Client Funds.

See [Commissioning](./COMMISSIONING.md), [Operating Model](./BANK_OPERATING_MODEL.md), [Offering Runbook](./OFFERING_RUNBOOK.md), and [Architecture](./ARCHITECTURE.md).
