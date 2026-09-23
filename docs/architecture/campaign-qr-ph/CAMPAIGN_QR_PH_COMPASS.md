# Campaign QR Ph Compass

Last updated: 2026-09-23

## North Star

A reusable campaign QR Ph can accept many provider payments. Each qualifying
settled payment is recognized exactly once, binds authoritative provisional
coverage, creates a first-class settlement envelope containing that coverage
snapshot from inception, and issues one zero-value completion Pay Code.

## Current Position

Current gate: **Gate 3 complete — ready for Gate 4 recognition boundary**

Current state: **Campaign payment QR configuration is immutable**

Prior checkpoint: **Reusable multi-payment characterization complete**

The existing payment-purpose standing-address path is now explicitly
characterized and regression-protected. It persists immutable provider
observations and classifies them without creating Account Funding receipts,
Client Funds credits, or Treasury operations. Repeated synchronization is
idempotent at the provider observation boundary.

At the characterization checkpoint, the installed standing-address persistence
did not retain the optional provider-neutral payer identity DTO. EMI Core
`v2.0.1` and EMI NetBank `v2.3.8` now provide the encrypted evidence contract
and mapping, and x-change is integrated against them pending its release and
host migration. No coverage, settlement envelope, completion Pay Code,
notification, or business application has been added.

The first live provisioning attempt also proved that purpose isolation fails
closed. The sandbox's legacy `netbank-mobile-v1` address for `09173011987` is
already bound to Account Funding, so x-change refused to bind the same provider
destination to purpose `payment`. The sandbox does not currently have the
preferred `netbank-account-hmac-v2` key configured.

After explicit operator authorization, the otherwise-unbound test mobile
`09175180722` produced characterization address
`01M3656EXH0FWZGJKBPGXCNT8J`. Its persisted posture is `payment`,
`observe_only`, `active`, reusable, static P2M, and without an embedded amount.
The pre-payment provider synchronization returned zero observations, zero
settlements, zero suspense cases, and zero applications. It created no Account
Funding receipt. The QR artifact remains private local characterization
evidence.

The operator then paid ₱5.37 through the reusable QR. NetBank returned one
settled InstaPay observation with a stable provider transaction identifier, a
provider operation identifier, exact-destination verification, PHP currency,
and identical occurred/settled timestamps. Two consecutive synchronizations
converged on one immutable observation. There were zero Account Funding
receipts, zero suspense cases, and zero financial applications. Payer identity
was not retained by the standing-address normalization.

The operator then paid ₱6.23 through the same QR. NetBank returned two distinct
provider transaction identities, proving the QR is reusable for independent
payments. The immutable evidence ledger contains three rows: one for the
second transaction and two payload versions for the first transaction. The
later first-transaction version refined `settled_at` by six seconds without
changing amount, currency, or settled status. Immediate replay added no new
row. Economic-payment identity must therefore be distinct from immutable
evidence-version identity.

`ReduceProviderFundingTransactionEvidence` now projects those immutable
versions into `CanonicalProviderFundingTransactionData`. A read-only run over
the live evidence returned exactly two settled transactions—₱5.37 from two
compatible evidence versions and ₱6.23 from one—with verified destinations.
The reducer emits only hashed transaction/operation/request keys and fails
closed on incompatible economic, routing, identity, time, or status changes.

A redacted live shape check proved both payments contain provider-reported
payer name, source account, and institution code, while explicit payer mobile
is absent. The account is not inferred to be a mobile number. EMI Core now has
an additive encrypted payer-evidence schema and NetBank now maps only those
provider-returned fields with `providerVerified=false`. Raw payer values remain
outside metadata, canonical projections, journals, and broadcasts. The
standing-address adapter also consumes NetBank's bounded multi-page iterator.

Scheduled reusable-address observation is now cadence-bound as well as
batch-bound. Active addresses remain eligible for their full active lifetime,
but only never-checked or due addresses enter each fair oldest-first batch.
NetBank history remains bounded to ten 100-row pages and fails closed when the
bounded view is exhausted.

Reversal semantics are intentionally conservative. Adverse or undocumented
status evolution—including any transition away from settled—is incompatible
canonical evidence and cannot trigger a business side effect. Exact NetBank
mapping is deferred until provider documentation or controlled live evidence
shows whether a reversal mutates the credit or arrives as a separate debit.

Campaign entry is now explicit: ordinary endpoint campaigns use
`pay_code_on_open`, while reusable campaign QR Ph uses
`reusable_payment_qr`. The latter binds one exact campaign template revision
to one active payment-purpose Standing Funding Address and its active static QR
artifact. Provider, currency, amount mode, availability, and permitted-payment
rules are hashed into an immutable configuration. Identical retries converge;
conflicting or stale bindings fail closed.

Redacted evidence: [Gate 1 live characterization report](reports/001-netbank-live-characterization.md).

## Settled Decisions

- Endpoint campaigns and campaign QR Ph are separate explicit entry modes.
- Payment monitoring remains a fact observer, not a business-application
  engine.
- `ProvisionalCoverage` is the authoritative legal/operational fact.
- The settlement envelope is created atomically with coverage and contains an
  immutable coverage snapshot/reference in its first version.
- The initial driver key is
  `aui.personal-accident.provisional-cover@1.0.0`.
- The driver is deferred until provider evidence and generic envelope
  contracts are ready.
- Completion Pay Codes are zero-denominated and use the existing `/x/claim`
  execution model.

## Evidence at This Checkpoint

Protected by `StandingFundingAddressProtocolTest`:

- payment-purpose observations are classified;
- duplicate polling does not duplicate immutable provider observations;
- no Account Funding receipt is created;
- no Client Funds balance changes;
- no Treasury inventory operation is created.

Protected by `CampaignQrPhPlanTest`:

- package ownership is explicit;
- coverage/envelope atomicity is explicit;
- the driver and field-ownership boundaries are explicit;
- live payment is not implicitly authorized.

## Open Questions Requiring Provider Evidence

1. Which NetBank identifier is stable across list/detail/webhook views?
2. Can one QR receive multiple independent transactions until explicit expiry?
3. How are reversals, returns, and late settlement represented?
4. Which payer fields are reliably present and provider-verified?
5. What pagination/window rules prevent missed or duplicated observations?
6. Does NetBank impose an expiry independent of x-change campaign policy?

## Stop Conditions

Stop before business recognition if any of these remain ambiguous:

- provider transaction identity;
- exact campaign/address binding;
- settlement finality;
- amount or currency interpretation;
- replay/idempotency behavior; or
- evidence provenance.

## Next Controlled Gate

Gate 4a: persist an operator-visible quarantine/attention record when canonical
provider evidence is incompatible, adverse, or unknown. Prove it has zero
coverage, envelope, Pay Code, Client Funds, or Treasury side effects. Only
then implement qualifying-payment recognition against the immutable Gate 3
binding.

See [the implementation plan](CAMPAIGN_QR_PH_PLAN.md).
