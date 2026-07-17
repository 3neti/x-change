# x-change Treasury Consumer Review — Wallet Phase 0–5

Date: 2026-07-17

## Outcome

This slice reviews `3neti/wallet` Treasury Phase 0–5 from the x-change / Cockpit consumer side.

The result is **consumer mapping only**. x-change does not wire wallet Treasury runtime, does not depend on wallet Treasury classes, and does not change money movement.

## Wallet Treasury State Reviewed

The wallet package now has:

- Phase 0 — Treasury architecture documentation;
- Phase 1 — current wallet behavior characterization;
- Phase 2 — planning-only Treasury DTOs and `TreasuryPlanningContract`;
- Phase 3 — `NullTreasuryPlanningRuntime` bound as the default planning runtime;
- Phase 4 — wallet-backed Inventory read model baseline;
- Phase 5 — Allocation and Slice read-model planning scaffold.

The wallet state is green through Phase 5, but still non-persistent and non-money-moving.

## x-change Bridge Concepts

x-change currently exposes these read-only money semantics:

| x-change concept | Current owner | Meaning today |
| --- | --- | --- |
| wallet balance | x-change wallet access bridge | current wallet accounting balance |
| outstanding Pay Code liability | x-change voucher liability bridge | active unredeemed Pay Code amount estimate |
| usable balance estimate | x-change bridge estimate | wallet balance minus outstanding Pay Code liability |
| money movement model | x-change planning seam | current debit-at-issuance, recommended reserve-at-issuance/debit-at-redemption |
| lifecycle trigger matrix | x-change planning seam | future trigger ownership for issue, redeem, partial claim, expiry, cancellation, provider failure |

These are bridge facts, not durable Treasury facts.

## Wallet Treasury Consumer Mapping

| x-change bridge fact | Wallet Treasury Phase 4/5 counterpart | Consumer decision |
| --- | --- | --- |
| wallet balance | `TreasuryInventoryReadModelData.walletBalanceMinor` | same accounting concept, but wallet remains source |
| outstanding Pay Code liability | future Allocation/Slice outstanding facts | not equivalent yet; wallet currently returns absent facts |
| usable balance estimate | `usableAmountMinor` only when Treasury facts are present | keep x-change estimate until real Treasury facts exist |
| active issued Pay Codes | future Allocation facts | x-change remains commercial source until wallet has persisted allocations |
| partial claim remaining balance | future Slice facts | x-change remains commercial source until wallet has persisted slices |
| expiry/cancellation release | future Release operation/read model | planning only; no release behavior exists |
| provider failure reversal | future Reversal operation/read model | planning only; no reversal behavior exists |

## Consumer Decision

For now, Cockpit should continue to show x-change bridge values with explicit bridge wording:

- `Outstanding Pay Codes`;
- `Usable Balance`;
- `Money Movement Model`;
- `Money Movement Triggers`.

Wallet Treasury read models should not replace those values yet because wallet Phase 4/5 explicitly reports absent Treasury facts.

## Future Adapter Direction

When x-change is ready to consume wallet Treasury directly, the adapter should:

1. Resolve wallet Treasury read-model contracts only through optional host/package availability.
2. Treat `hasTreasuryFacts = false` and `treasury_facts = absent` as a disconnected/bridge state.
3. Prefer wallet Treasury `usableAmountMinor` only when real Treasury facts are present.
4. Keep x-change voucher liability bridge visible until Allocation/Slice facts are durable.
5. Never treat `NullTreasuryPlanningRuntime` results as executed allocations, draws, releases, repayments, or reversals.

## Boundaries Preserved

- No wallet Treasury runtime dependency was added to x-change.
- No wallet package dependency was added to `packages/x-change/composer.json`.
- No direct wallet Treasury class imports were added to x-change production code.
- No wallet reservation, capture, release, repayment, or reversal behavior changed.
- No wallet balance computation changed.
- No voucher lifecycle behavior changed.
- No Cockpit UI behavior changed.
- No provider, execution, journal, action, feedback, campaign, public API, or lifecycle mutation behavior changed.

## Recommended Next Step

Keep wallet Phase 5 committed, then choose one of two separately approved paths:

1. **x-change Treasury Optional Adapter Planning** — define an optional read-only adapter contract that can consume wallet Treasury read models when the host has wallet Treasury available.
2. **Wallet Treasury Phase 6** — continue wallet-side read-model/persistence planning before x-change consumes it.

Do not implement real money movement until wallet owns durable Inventory, Allocation, Slice, Draw, Release, Repayment, and Reversal invariants.
