# Public Auto-Generate Compass

Last updated: 2026-10-07

## North Star

Anyone may open `/x/auto-generate`, compose a Pay Code, pay the complete
authoritative cost, and receive exactly one Pay Code issued by the commissioned
Commercial Principal—without receiving an x-change Account, Cockpit access,
wallet, or Client Funds.

## Current position

Status: **Testing-instance Gates 0, 1, and 2 complete. Payable-order creation
and payment remain separately authorized.**

The operative testing-instance sequence is now tracked in the
[Testing-Instance Acceptance Plan](TESTING_INSTANCE_ACCEPTANCE_PLAN.md) and
[Testing-Instance Acceptance Compass](TESTING_INSTANCE_ACCEPTANCE_COMPASS.md).
As verified on 2026-10-07, the testing environment is commissioned and
operational, its public surfaces return HTTP 200, and public URLs are HTTPS.
Live discovery, estimate, and handoff acceptance returned the authoritative
PHP 25.00 principal, PHP 15.00 fee, and PHP 40.00 total without changing any
order, voucher, Treasury, provider, wallet, or queue fact. Gate 2 then adopted
x-change `v1.0.108` and ran the authenticated rollback lifecycle once with the
scenario's current PHP 25.00 principal and PHP 18.00 service fee. It projected
exactly one Pay Code and normal stamp/share output inside the transaction,
reported zero provider calls and no persisted value, and left the complete
live mutation baseline unchanged.

Gate 3 then created exactly one bounded public payable order for a PHP 25.00
principal under the PHP 40.00 authoritative-total ceiling. The order is
`awaiting_payment` with `full_amount` funding and no voucher, Treasury hold,
payment evidence, settlement, provider observation, wallet effect, queued job,
or Standing Funding run. No real payment is authorized yet.

The required financial engine is already proven:

- authoritative Quick Generate compilation and pricing;
- On-Demand Issuance `full_amount` funding;
- exact Bank Transfer and fixed-amount direct QR Ph;
- persistent funding workspace and automatic verification;
- order-bound Treasury holds;
- exactly-once issuance through `GeneratePayCode`;
- normal stamp and sharing presentation; and
- Gate 10 handling for late, mismatched, duplicate, and reversed evidence.

The package now has the public surface, shared workspace, server-only
Commercial Principal resolution, forced `full_amount` funding, possession
tokens, browser-session binding, active-order resumption, and public order
throttles. Existing Cockpit routes remain separate and unchanged.

The package-owned browser runner now proves the exact path with a ₱25.00
principal and the current ₱43.00 authoritative total. It reports six lifecycle
steps, exactly one projected Pay Code and stamp/share result, zero provider
calls, and a confirmed full rollback. Shared On-Demand Issuance tests cover
mismatched, duplicate, late, expired, and reversed payment evidence.

The Commercial Principal remains non-login infrastructure. It implements only
the internal actor and wallet/payment capabilities required to pass through the
existing issuance engine. It has no email, mobile number, password, auth
provider mapping, login route, or Cockpit access.

## Settled decisions

1. `/x/auto-generate` is public and does not require a Laravel user login.
2. The Commercial Principal remains the issuer of record.
3. The System runtime executes the commissioned authority.
4. The visitor is a requester and payer, not an institutional issuer.
5. The browser cannot select or submit the principal identity.
6. Public issuance is enabled by default during the proof phase.
7. An environment variable provides an immediate kill switch.
8. Commissioning completeness and one active Commercial Principal remain
   mandatory.
9. The public surface always uses `full_amount`.
10. The visitor receives no Account, wallet, or Client Funds.
11. Bank Transfer remains primary; fixed-amount QR Ph is secondary.
12. The existing issuance, funding, and stamp engines are reused.
13. The Quick Generate workspace is componentized rather than copied.
14. Cockpit and public pages use separate layouts and backend profiles.
15. Guest orders use hashed possession tokens and browser-session binding.
16. Order references alone grant no authority.
17. CAPTCHA is deferred until after workflow proof.
18. Turnstile will later protect payable-order creation only.
19. Maker/Checker approval is not required for this commissioned service.
20. The feature becomes a versioned commissioning option after proof.

## Invariants

- No public request can select or impersonate a Commercial Principal.
- No Pay Code exists before authoritative payment and settlement.
- No guest-controlled value becomes generally spendable Client Funds.
- No guest can inspect another guest's order.
- No client-side surface profile can weaken backend authorization.
- No hidden Vue field can change issuer, Account, funding basis, or price.
- No acknowledgement, polling response, or Turnstile token is payment evidence.
- No duplicate provider evidence can issue a second Pay Code.
- No public-page extraction changes authenticated Cockpit issuance behavior.
- No disabled entry point strands an already paid order.

## Default proof target

```yaml
public_auto_generate:
  enabled: true
  route: /x/auto-generate
  authenticated_user_required: false
  issuer: commissioned_commercial_principal
  funding_basis: full_amount
  primary_method: bank_transfer
  secondary_method: fixed_amount_qr_ph
  captcha: deferred
```

## Immediate next gate

**Testing-instance Gate 4 — One exact real payment, subject to new explicit
authorization.**

Gate 2 is complete. The rollback dependency repair is immutable in x-change
`v1.0.108`; focused and adjacent suites passed 43 tests with 271 assertions;
the authenticated Cloud runner proved same-order replay, exactly-once projected
issuance, full rollback, zero provider calls, and no retained financial,
wallet, queue, or Standing Funding state.

Gate 3 is complete for order `01M4B6MS7DA7SMEF1WY76VYHVJ`. Do not make a real
payment unless Gate 4 independently names the PHP 40.00 ceiling, payment rail,
payer, and observation window.

## Following gates

1. Perform separately authorized testing-instance acceptance.
2. Make the policy commissionable.
3. Add Turnstile as a later security adapter.

## Deferred TODO

- [ ] Design a curated **Public Offerings** catalog as a distinct,
  commissioned public product menu.
- [ ] Keep the current public composer blank-only until that catalog passes
  authorization, audit, versioning, abuse-control, and browser-acceptance
  gates.
- [ ] Prove that public visitors can only select published offering snapshots
  and can never inspect, edit, save, or reuse private Cockpit templates.
- [ ] Define audited publish, pause, withdraw, and replacement semantics that
  preserve already paid and issued orders.

## Stop conditions

Stop rather than improvise if:

- public issuance requires a second pricing or issuance engine;
- the principal must be accepted from browser input;
- the guest must receive Client Funds to complete the flow;
- another browser can access an order by reference alone;
- disabling the public route would abandon a paid order;
- the public profile can be bypassed by changing Vue state;
- exact payment evidence cannot bind to one order; or
- current Cockpit issuance behavior regresses during extraction.

## Companion documents

- Plan: `docs/architecture/public-auto-generate/PUBLIC_AUTO_GENERATE_PLAN.md`
- Testing-instance acceptance plan:
  `docs/architecture/public-auto-generate/TESTING_INSTANCE_ACCEPTANCE_PLAN.md`
- Testing-instance acceptance compass:
  `docs/architecture/public-auto-generate/TESTING_INSTANCE_ACCEPTANCE_COMPASS.md`
- On-Demand Issuance plan:
  `docs/architecture/on-demand-issuance-funding/ON_DEMAND_ISSUANCE_FUNDING_PLAN.md`
- On-Demand Issuance compass:
  `docs/architecture/on-demand-issuance-funding/ON_DEMAND_ISSUANCE_FUNDING_COMPASS.md`

Future agents must update this compass after every completed or blocked gate.

## 2026-10-07 Update — Replacement Evidence Isolation

- A live acceptance payment revealed that evidence was claimed before exact
  intent matching.
- The old expired PHP 40.00 intent could therefore claim the replacement PHP
  40.01 observation and force the exact replacement intent into duplicate
  evidence suspense.
- Verification now classifies mismatched evidence before the immutable claim
  boundary. Only an exact eligible intent may attempt the exclusive claim.
- Underpayment, excess-payment, provider mismatch, duplicate evidence, and
  exact late-payment semantics remain fail-closed and covered by tests.
- Existing live suspense and evidence records are deliberately unchanged.
  Reconciliation, claim reassignment, settlement, issuance, and any further
  payment remain separate authority gates.
- Immutable x-change `v1.0.109` at `bd1c0f05` was adopted by lock-only host
  commit `ed3d7190` and deployed successfully as
  `depl-a2ecdfd3-531f-46e9-940d-5d4ac6f29402`.
- Cloud verification confirmed operational commissioning, strict doctor 37 of
  37, Horizon disabled, no authorized Redis queues, Standing Funding disabled
  at generation 7, and no change to the existing claim, suspense, settlement,
  voucher, or Treasury-hold facts.
- Gate 4A is closed before reconciliation. A guarded disposition of the
  pre-repair live records is a separately authorized Gate 4B.
