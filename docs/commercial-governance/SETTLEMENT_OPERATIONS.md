# Commercial Settlement Operations

Commercial allocation and cash settlement are separate events.

An accepted Pay Code charge posts the governed waterfall immediately. Provider cost becomes a Provider Cost Payable Position; attributed commission becomes a Partner Commission Payable Position. Neither posting claims that cash has left the provider account.

## Provider Cost

Provider cost is settled only from an authoritative provider statement, invoice, or account-debit record. The operator records one period batch with `x-change:commercial:provider-cost:record`. x-change matches eligible provider-cost allocations by provider, connection, currency, and accepted-sale period.

- An exact aggregate records the evidence, settles every matched payable once, and reduces Provider Inventory by the observed outflow.
- A missing allocation, amount difference, or empty period records `review_required`. It does not change a Position or Inventory and it does not reserve the candidate allocations against corrected evidence.
- Reusing an idempotency key with changed evidence fails closed.

Provider invoices that have not produced a cash movement are evidence of a payable, not authority to reduce Provider Inventory.

## Partner Commission

Commission is earned only when the immutable Commercial Sale snapshot attributes an eligible partner. Payment aggregates all unpaid allocations for one partner, provider connection, currency, and period.

1. A maker with `commercial.commissions.request` runs `x-change:commercial:commission:request`. The receiving destination is validated, encrypted at rest, and exposed only as a masked summary.
2. A different checker with `commercial.commissions.approve` runs `x-change:commercial:commission:approve` with an independent approval reference.
3. An execution operator with `commercial.commissions.execute` runs `x-change:commercial:commission:submit --confirm-live`. Submission remains disabled unless `XCHANGE_COMMERCIAL_LIVE_PROVIDER_CALLS_ENABLED=true`.
4. `x-change:commercial:commission:reconcile` queries the provider. Pending does nothing. Rejected leaves the payable and Inventory intact. Only an authoritative completed status settles the Partner Commission Payable Position and reduces Provider Inventory.

The non-interactive system principal cannot be maker, checker, or execution operator. The request and approval capabilities cannot be granted to the same person. Provider transaction identifiers, Treasury operation references, and every lifecycle state are journaled.

## Operational Invariants

- Commercial revenue, provider cost, and commission are not Client Funds.
- Publication maker-checker and payout maker-checker are independent capability pairs.
- A provider response never recomputes old sales from the current Price List.
- A rejected commission payout does not erase earned commission.
- UI access is informational unless the signed-in operator has the specific capability.
- No installation, migration, scheduler, or page load submits a commercial payout.
- Live acceptance always requires a human-confirmed amount, destination, and provider gate.

The Cockpit Commercial Controls **Operations** tab shows outstanding payable amounts, evidence batches, payout batches, masked destinations, and lifecycle status. Ordinary Account holders cannot access this workspace.
