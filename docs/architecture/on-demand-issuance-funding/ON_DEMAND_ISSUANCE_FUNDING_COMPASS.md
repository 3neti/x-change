# On-Demand Issuance Funding Compass

Last updated: 2026-09-29

## North Star

A user can freeze one Pay Code instruction, receive exact payment directions,
pay through a provider-backed method, and receive exactly one issued Pay Code
after authoritative settlement. The money never becomes generally spendable
between receipt and issuance.

## Current position

Status: **Controlled bank-transfer baseline, fixed-amount QR presentation,
collision-safe amount leasing, scheduled order expiry, and late-payment Client
Funds disposition are accepted locally; live-provider acceptance remains
gated.**

Existing reusable foundations:

- Quick Generate already compiles and prices an issuance payload;
- `GeneratePayCode` already owns authoritative funding checks and issuance;
- Funding Intents already provide encrypted instructions, guarded transitions,
  provider verification, settlement, and idempotency;
- x-change already has collision-safe exact bank-transfer amount reservations;
- the Cockpit has a reusable funding-method selector;
- `3neti/wallet` already owns Treasury Positions, reservations, allocations,
  releases, reversals, locking, and idempotency; and
- Treasury allocation reads have a durable package-owned baseline.

Implemented foundations:

- provider-neutral order-bound hold operations in `3neti/wallet`, built on the
  existing durable Treasury allocation runtime;
- disabled-by-default application policy with `full_amount` and `shortfall`;
- immutable, encrypted, versioned Issuance Funding Orders and append-only
  events;
- purpose-scoped Funding Intents;
- Bank Transfer-first selector projection with exact, read-only amount;
- fixed-amount QR Ph kept disabled by default and exposed only when an installed
  provider returns an order-specific embedded-amount QR;
- Pay Code funding kept unavailable until proven;
- authoritative settlement into the hold;
- unique after-commit issuance resumption through `GeneratePayCode`;
- owner-scoped polling, acknowledgement, and governed cancellation routes;
- a non-dismissible funding modal; and
- active-order restoration after a page reload;
- standing-account bank-transfer directions that do not provision a VCA; and
- fail-closed instruction errors that enter an auditable, cancellable attention
  state instead of remaining falsely payable;
- provider/connection/currency-scoped exact-amount leases protected by a
  distributed lock and a database uniqueness constraint;
- smallest-available minor-unit reconciliation adjustments for concurrent
  same-value orders;
- a cooling period that prevents cancelled or expired amounts from being
  immediately reassigned;
- scheduled order expiry and hold release; and
- verified late payments credited to Client Funds without reviving or issuing
  the terminal order.

Focused proof passes for settlement, hold placement, exactly-once resumption,
owner restoration, standing-account instruction presentation, selector
presentation, and frontend modal behavior. The local sandbox browser proof
froze a PHP 25.00 instruction, displayed the configured NetBank account and
exact amount, accepted synthetic provider evidence, placed and consumed the
Treasury hold, and issued Pay Code `453S`. Replaying the exact resume job left
the same voucher and four order events. No real provider call or money movement
was used.

Still gated:

- live-provider acceptance; and
- package publication or deployment.

Gate 3 local evidence:

- NetBank continues to own the live fixed-amount dynamic QR contract;
- x-change uses that provider contract only behind
  `XCHANGE_ON_DEMAND_FIXED_QR_PH_ENABLED`;
- the Cockpit keeps Bank Transfer primary and renders the provider QR only when
  `embedded_amount=true`;
- `on_demand_issuance_fixed_qr_demo` is package-owned, browser/command
  invokable, rollback-only, and makes zero provider calls;
- the runner proves a second identical order receives a distinct leased amount,
  an unpaid order expires while retaining its cooling boundary, and a signed
  late payment becomes Client Funds without issuing or reviving the Pay Code;
  and
- focused successful-settlement coverage continues to prove the active order
  moves funds into the hold and resumes `GeneratePayCode` exactly once.

Gate 4 local evidence:

- the Funding page exposes the simulations through a separate rollback-only
  Lifecycle Lab rather than presenting them as a real funding method;
- the package-owned browser runner displayed an embedded PHP 25.00 QR,
  separated a concurrent order at PHP 25.01, expired the original order, and
  disposed a signed late payment to Client Funds without issuing a Pay Code;
- the run reported zero provider calls, no monetary value, and full rollback;
- 24 focused backend tests passed with 170 assertions;
- all 1,141 frontend tests passed, including the focused Funding-page and
  modal coverage;
- the broader backend matrix still exposes six pre-existing claim unit-test
  failures in unchanged named-slice and Mockery-fixture coverage; none of the
  affected implementation or test files differs from `v1.0.65`; and
- the sandbox production asset build completed successfully.

## Settled decisions

1. On-Demand Issuance is an application-wide feature.
2. Package default is disabled for backward compatibility.
3. Enabled basis defaults to `full_amount`.
4. `shortfall` is the only alternate basis in this version.
5. There are no user, Account, campaign, or template overrides.
6. Bank Transfer is primary and selected by default in the modal.
7. QR Ph is secondary and must be fixed amount.
8. Pay Code funding remains gated.
9. Bank Transfer uses a collision-safe exact-amount lease.
10. Service fees are not treated as sufficient randomization.
11. The unadjusted amount is preferred; an identifying adjustment is added
    only for an active collision.
12. The adjustment is disclosed and is not fee, principal, or revenue.
13. Payer acknowledgement triggers verification but is not evidence.
14. The modal remains open until issuance, explicit cancellation, or attention.
15. Refresh restores the active order and modal state.
16. `GeneratePayCode` remains the only issuance authority.
17. The hold is not another Bavix wallet.
18. Generic hold/allocation mechanics belong in `3neti/wallet`.
19. On-demand commercial meaning and orchestration belong in x-change.

## Invariants

- No Pay Code exists before sufficient authoritative funding.
- No browser action can verify or settle a payment.
- No open-amount QR is presented as suitable for on-demand issuance.
- No wrong or ambiguous bank-transfer amount auto-issues.
- No settled order-bound amount becomes generally spendable while issuance is
  pending.
- No duplicate webhook, poll, retry, refresh, or acknowledgement can issue a
  second Pay Code.
- No pricing or instruction mutation changes an existing funding order.
- No Treasury adjustment is classified as revenue without a governed fee rule.
- No x-change model or Pay Code concept enters the wallet package contract.

## Current default target

```yaml
on_demand_issuance:
  enabled: true
  basis: full_amount
  primary_method: bank_transfer
  secondary_method: qr_ph
  pay_code_method: disabled
```

This is the shared-beta target, not the backward-compatible package default.

## Next controlled gate

**Gate 5 — Controlled dependency publication and host adoption.**

1. publish the wallet hold primitive before x-change consumes a released
   version;
2. synchronize the x-change dependency lock and rerun the release matrix;
3. publish x-change only with explicit authority;
4. upgrade the sandbox from the released packages and repeat the browser
   lifecycle; and
5. keep live-provider acceptance, Cloud deployment, and real payment behind
   separate authority.

No publication, tag, push, Cloud deployment, provider call, or real transfer is
authorized by this compass update.

## Following gate

After Gate 4, consider a separately authorized publication gate. Live provider
testing remains a later, independently authorized operation.

## Stop conditions

Stop rather than improvise if:

- the wallet primitive requires an x-change or voucher dependency;
- the provider cannot prove fixed-amount QR capability;
- exact-amount reservations cannot be made unique within the active scope;
- a fee or reconciliation adjustment would be treated as ungoverned revenue;
- settlement would expose the received value as unrestricted Client Funds;
- an operation cannot be made database-idempotent; or
- testing would require a real provider call or money movement.

## Companion documents

- Plan: `docs/architecture/on-demand-issuance-funding/ON_DEMAND_ISSUANCE_FUNDING_PLAN.md`
- Wallet plan: `3neti/wallet: docs/architecture/treasury/ON_DEMAND_ISSUANCE_HOLD_PLAN.md`
- Wallet compass: `3neti/wallet: docs/architecture/treasury/ON_DEMAND_ISSUANCE_HOLD_COMPASS.md`

Future agents must update this compass after every completed or blocked gate.
