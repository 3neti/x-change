# On-Demand Issuance Funding Compass

Last updated: 2026-09-30

## North Star

A user can freeze one Pay Code instruction, receive exact payment directions,
pay through a provider-backed method, and receive exactly one issued Pay Code
after authoritative settlement. The money never becomes generally spendable
between receipt and issuance.

## Current position

Status: **The bank-transfer and fixed-amount direct-QR lifecycle is shipped
through x-change v1.0.74 and has passed a real low-value GCash acceptance run.
Gate 10 recovery hardening is complete locally; publication and host adoption
are the next controlled gate.**

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
- one shared NetBank direct-QR service now owns the proven `/x/pay` and
  on-demand fixed-QR instruction shape;
- explicit `direct_qr` and `registered_vca` modes with no automatic fallback;
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
  the terminal order;
- explicit underpayment, excess-payment, and ambiguous-evidence states that
  cannot issue or masquerade as an unseen payment;
- durable provider-scoped evidence claims that prevent one transaction from
  funding two orders while preserving same-intent idempotency; and
- a durable provider-reversal marker that blocks queued issuance independently
  of queue timing.

Focused proof passes for settlement, hold placement, exactly-once resumption,
owner restoration, standing-account instruction presentation, selector
presentation, and frontend modal behavior. The local sandbox browser proof
froze a PHP 25.00 instruction, displayed the configured NetBank account and
exact amount, accepted synthetic provider evidence, placed and consumed the
Treasury hold, and issued Pay Code `453S`. Replaying the exact resume job left
the same voucher and four order events. No real provider call or money movement
was used.

Still gated:

- publication and sandbox/testing adoption of the completed Gate 10 slice;
- operator workflow for resolving underpayment, excess, and ambiguous evidence;
- Pay Code funding; and
- registered-VCA activation until NetBank enables pre-transaction validation.

## Shipped release evidence

| Release | Accepted evidence |
| --- | --- |
| `v1.0.69` | Strict-doctor funding-basis validation. |
| `v1.0.70` | Persistent funding workspace and issued-stamp handoff. |
| `v1.0.71` | Corporate-account bank-transfer verification. |
| `v1.0.72` | Correct corporate account presentation. |
| `v1.0.73` | Automatic modal payment polling and retry UX. |
| `v1.0.74` | Correct issued Pay Code amount and stamp presentation. |

The sequence culminated in an authorized PHP 25.03 GCash direct-QR payment.
The provider payment was detected and exactly one Pay Code was issued through
the normal authority. This retires the former “live direct-QR acceptance”
blocker.

## Gate 10 local evidence

- settled short payments become `underfunded` and stop automatic polling;
- settled excess payments become `payment_ambiguous` and cannot issue;
- one provider transaction can be claimed by only one Funding Intent;
- a same-intent evidence replay is idempotent;
- duplicate cross-intent evidence is rejected into suspense;
- an authoritative provider reversal moves a pre-issue order to
  `issuance_attention` and the queued job refuses to issue; and
- focused on-demand, settlement, webhook, and reversal suites pass.

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
20. Direct QR embeds the amount but does not claim provider-side registration
    or limit enforcement.
21. Registered VCA is an explicit operator-selected mode and never a hidden
    fallback.
22. One provider transaction can fund only one Funding Intent.
23. Underpayment, excess payment, and duplicate evidence require review and do
    not expose safe cancellation or continued automatic polling.
24. A provider reversal marker has priority over a queued issuance job.

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

**Gate 13 — Controlled Gate 10 publication and host adoption.**

1. review the Gate 10 schema and money-safety diff;
2. publish x-change only with explicit authorization;
3. upgrade the sandbox and run migrations;
4. rerun exact, underpayment, excess, duplicate-evidence, late-payment, and
   provider-reversal tests;
5. deploy testing only under separate authorization; and
6. keep Pay Code funding and registered-VCA activation disabled.

No publication, tag, push, Cloud deployment, provider call, or real transfer is
authorized by this compass update.

## Following gate

After Gate 13, implement an operator-facing resolution workflow for recovered
underpayment, excess, and ambiguous evidence. Pay Code funding remains a later,
independently reviewed gate.

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
