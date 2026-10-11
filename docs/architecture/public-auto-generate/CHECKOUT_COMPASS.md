# Checkout and Owner Payments Console Compass

Last updated: 2026-10-11

## North Star

A person can prepare one Pay Code purchase, pay once, return after interruption,
and see a durable disposition: issued Pay Code or governed refund. The
commissioned Commercial Principal owns issuance; a Contact identifies a
person associated with the checkout; settled provider evidence proves money
received. None of those roles silently substitutes for another.

## Current position

The package now persists drafts and placed Checkouts, gates bank instructions
on a valid visitor mobile, links a Contact after settled provider evidence,
and exposes a password-gated owner console with revocable read-only viewer
links. A paid issuance failure may enter a manual external-refund case. The
Commercial Principal remains non-interactive. Pay Code funding and automatic
refund execution remain disabled. This implementation is local and has not
passed the paid-journey or deployment gates below.

## Settled decisions

1. Checkout belongs in x-change, while Contact remains in the contacts
   package. The model is reusable for authenticated purchase-style issuance,
   but public integration comes first.
2. Keep `/x/auto-generate` as buyer entry and recovery. `/x/checkout` is the
   temporary owner payments console and future admin-cockpit module.
3. The entire console is gated. Stakeholder links are read-only. An owner
   session may perform guarded retry and manual-refund-case actions.
4. A shared password is acceptable for this beta only if production uses a
   strong deployment secret. Literal `password` is local/test only. Shared
   access has limited person-level attribution; record session and request
   evidence and replace it with named admin identity later.
5. Bank Transfer requires visitor mobile before server disclosure of bank
   instructions. QR Ph may supply valid provider payer identity after
   settlement. Typed mobile and provider sender data have distinct provenance.
6. Pay Code funding and automatic refunds are deferred. Manual external
   refunds require an auditable case and confirmed evidence.

## Invariants

- Checkout placement is idempotent. One checkout links to no more than one
  payable order; a settled order never creates a second payment obligation.
- The funding order, Settlement, and provider observation own financial truth.
  Console projections, Contact fields, and Horizon job metrics do not prove
  payment.
- Bank-transfer destination and account details are absent from public
  projections until the bank mobile gate passes. The exact amount remains
  visible because the visitor can choose QR Ph without a typed mobile.
- Existing paid orders remain recoverable while new orders are paused.
  Funded holds cannot be released by an unpaid cancellation path.
- Issuance and completed refund are mutually exclusive, guarded by row locks,
  idempotency, and an append-only audit trail. A manual refund is not complete
  until external return evidence and Treasury reconciliation are recorded.
- A Contact mobile is a communication identifier until control or provider
  identity is verified. Never use visitor input alone to reveal KYC data,
  authorize a refund destination, or override provider payment evidence.
- Stakeholder capabilities cannot mutate orders. A public request's resolved
  Commercial Principal is not evidence of owner-console authentication.
- Production console unlock fails closed for missing, default, or literal
  `password` secrets. No password or possession token appears in logs or
  public page props.

## Acceptance evidence to record

Record the exact package and host commits, migration/backfill result,
selected tests, browser acceptance, queue and scheduler status, one QR Ph
and one Bank Transfer paid journey, a recoverable paid failure, and a
manually reconciled refund case. Record the deployed password policy and
viewer-link revocation check without exposing secret values. Do not treat a
healthy Horizon snapshot or an observed incoming transfer as proof of
successful issuance.

## Stop conditions and future migration

Keep new public placement paused if instruction disclosure, payment matching,
funded-order recovery, refund accounting, or console access fails acceptance.
Move the same scoped read model and guarded actions into the future admin
cockpit; replace shared-password sessions with named operators without
changing Checkout or funding history. Do not enable Pay Code funding until
cycle prevention, ownership, settlement, and refund behavior are proven.

Companion: [`CHECKOUT_PLAN.md`](CHECKOUT_PLAN.md).
