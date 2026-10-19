# Product Wallet Revenue Compass

Last updated: 2026-10-19

## Current position

The legacy local-revenue allocator now delegates integer Product transfers to
`3neti/wallet`. The commercial sale and Treasury waterfall remain unchanged.

## Invariants

- accepted quote totals are conserved;
- Pay Code principal stays in Pay Code Reserve;
- Product wallets receive only Product revenue allocations;
- provider and partner amounts retain their Treasury destinations; and
- retries cannot create a second Product transfer for the same allocation.

## Next gate

The live billable Product allocation bridge is still disabled. Read-only
reconciliation now proves quote conservation, Product-only lines, principal
separation, and shadow agreement with posted Treasury allocations. An immutable
reversal ledger shape is present; its execution path remains disabled until the
wallet movement and reversal tests are commissioned together.
The deterministic bridge plan now fixes the transfer and reversal references
without executing either operation.
The disposable commissioning simulator now proves posting, replay, reversal,
and reversal replay exactly once. The remaining gate is replacing the
simulator with a governed wallet adapter after the host database is commissioned.
The package now exposes that adapter contract with a fail-closed default; hosts
must explicitly bind an implementation before any movement can occur.
The governed adapter now delegates only when
`XCHANGE_COMMERCIAL_PRODUCT_REVENUE_BRIDGE_ENABLED=true` and a host movement
contract is bound. The default remains disabled.
The commissioning status service reports these two prerequisites without
executing a financial operation. A concrete host movement adapter still needs
its own authorization, idempotency, and reversal controls before activation.
The package now includes the narrow Bavix movement implementation and its
contract tests, but deliberately does not bind it globally. The host must bind
that implementation only after its commissioning review.
