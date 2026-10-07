# Public Auto-Generate Compass

Last updated: 2026-10-08

## North Star

Anyone may open `/x/auto-generate`, compose a Pay Code, pay the complete
authoritative cost, and receive exactly one Pay Code issued by the commissioned
Commercial Principal—without receiving an x-change Account, Cockpit access,
wallet, or Client Funds.

## Current position

Status: **Testing-instance Gates 0 through 4B complete. Gate 5 proved the live
issuance path but stopped fail-closed on a one-cent Client Funds residual. A
separate Gate 5A repair is required before acceptance can close.**

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

Gate 3 created one bounded public payable order. The Gate 4 payment exercise
later exposed the evidence-ordering defect repaired by Gate 4A. Gate 4B then
resolved the pre-repair evidence append-only and credited its expired-order
payment to Client Funds without issuance. Those historical orders are expired
and no longer participate in the acceptance path.

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

**Gate 5A — Amount-Lease Residual Containment Hardening.**

Gate 5A is proposed but not yet authorized. It must characterize the live
one-cent residual in tests, keep the collision-adjustment outside generally
spendable Client Funds, preserve exact-payment evidence matching, and prove
that hold placement, hold consumption, issuance, and replay remain
exactly-once. It must publish and adopt an immutable repair before a new live
acceptance payment is considered. Gate 5A authorizes no payment, claim,
redemption, payout, persistent Horizon process, scheduled Standing Funding,
or quarantine change by itself.

## Following gates

1. Complete Gate 5A residual-containment hardening under separate approval.
2. Repeat Gate 5 with a fresh, separately authorized exact payment.
3. Make the policy commissionable.
4. Add Turnstile as a later security adapter.

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
- Gate 4B is now authorized for claim 11, observation 2847983, source intent
  32, exact replacement intent 34, and suspense cases 1 and 2 only. The repair
  is append-only: the original claim remains immutable while one supersession
  records effective ownership. The expired replacement follows the existing
  late-payment rule and credits PHP 40.01 to Client Funds without issuance.
- Gate 4B completed on immutable x-change `v1.0.110` (`6bc14dbc`), adopted by
  host commit `97f97df9` and deployed as
  `depl-a2ecf75d-0449-412d-960e-7f0dcfa237bc`. Supersession 1 makes intent 34
  the effective owner of claim 11; settlement 19 credited PHP 40.01 to Client
  Funds and resolved cases 1 and 2. Both orders remain expired, and no voucher,
  Treasury hold, or queue job was created.
- The immediate replay in `cexe-a2ecfa0d-8f2b-4217-a5f7-270546f75099`
  returned identical state fingerprints. No further order or payment is
  authorized by this closure.

## 2026-10-08 Update — Gate 5 Awaiting Exact Payment

- The testing-only On-Demand Issuance lifetime is now 7,200 seconds. Deployment
  `depl-a2ed7bbc-5196-4dbe-8520-9f8e7a52c416` retained x-change `v1.0.110`,
  database queues, disabled Horizon, and disabled scheduled Standing Funding.
- Read-only discovery, estimate, and handoff returned PHP 25.00 principal, PHP
  15.00 fees, and PHP 40.00 authoritative total.
- Exactly one fresh order was created: `01M4C4PEEKKVKPR5EWKMHY19AK`
  (order 36, intent 35). The leased exact Bank Transfer amount is PHP 40.01,
  within the PHP 40.99 Gate 5 ceiling.
- The order is `awaiting_payment` with approximately 120 minutes from creation.
  No observation, claim, settlement, hold, voucher, queue job, or failed job
  exists for this exercise yet. The gate is paused for the operator's payment.

## 2026-10-08 Update — Gate 5 Issued but Stopped on Residual Containment

- The operator reported the exact PHP 40.01 Bank Transfer paid. The first
  bounded observation already found order 36 `issued`, intent 35 `settled`,
  provider observation 2847985, evidence claim 12, settlement 20, and voucher
  385.
- Sanitized inspection `cexe-a2ed80b2-058c-4799-bca6-527b83c0dfee` proved
  exactly one order, one effective claim, one settlement, and one voucher.
  Observation 2847985 was a settled PHP 40.01 NetBank observation matched only
  to intent 35.
- The order-bound Treasury path is internally consistent for the PHP 40.00
  authoritative issuance requirement: one PHP 40.00 reservation and
  allocation activation placed the hold, then one PHP 40.00 allocation draw
  consumed it. The allocation balance moved `0 -> 4000 -> 0` minor units.
- Voucher 385 was created and remains unredeemed. The public receipt rendered
  exactly one issued Pay Code and its HTTPS beneficiary link. No claim or
  redemption was executed.
- Both database queues remained empty. Horizon and scheduled Standing Funding
  remained disabled.
- Gate 5 nevertheless failed its Client Funds isolation invariant. The amount
  lease required PHP 40.01 while the hold protected PHP 40.00. The exact
  issuer's Client Funds balance moved from the documented PHP 80.02 baseline
  to PHP 80.03, leaving the PHP 0.01 reconciliation adjustment generally
  spendable after issuance. Read-only Treasury inspection
  `cexe-a2ed8173-1c5c-46cf-8b8e-888f67a02ad8` confirmed the PHP 80.03 balance.
- The gate therefore stopped before same-order replay. No second order,
  payment, repair, claim, redemption, payout, Horizon process, scheduled
  Standing Funding run, or quarantine change was attempted.
