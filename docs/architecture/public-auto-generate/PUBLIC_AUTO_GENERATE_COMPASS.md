# Public Auto-Generate Compass

Last updated: 2026-09-30

## North Star

Anyone may open `/x/auto-generate`, compose a Pay Code, pay the complete
authoritative cost, and receive exactly one Pay Code issued by the commissioned
Commercial Principal—without receiving an x-change Account, Cockpit access,
wallet, or Client Funds.

## Current position

Status: **Gate 8 rollback proof complete; testing-instance payment pending separate authorization.**

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

**Gate 8b — Separately authorized testing-instance acceptance.**

Completed locally:

1. rollback-only `public_auto_generate` lifecycle runner;
2. complete authoritative total and server-selected principal;
3. cross-session order isolation and idempotent resume;
4. exact-payment lifecycle plus shared mismatch, duplicate, late, expired, and
   reversal regressions;
5. exactly one projected Pay Code and the normal stamp/share result; and
6. operator browser report with no provider calls and complete rollback.

Next, only after explicit authorization:

1. publish the reviewed package version;
2. upgrade and deploy the testing host;
3. run the rollback browser report in that host; and
4. make one ₱25.00-principal public order payment, aborting if the
   authoritative total exceeds the separately approved ceiling.

## Following gates

1. Perform separately authorized testing-instance acceptance.
2. Make the policy commissionable.
3. Add Turnstile as a later security adapter.

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
- On-Demand Issuance plan:
  `docs/architecture/on-demand-issuance-funding/ON_DEMAND_ISSUANCE_FUNDING_PLAN.md`
- On-Demand Issuance compass:
  `docs/architecture/on-demand-issuance-funding/ON_DEMAND_ISSUANCE_FUNDING_COMPASS.md`

Future agents must update this compass after every completed or blocked gate.
