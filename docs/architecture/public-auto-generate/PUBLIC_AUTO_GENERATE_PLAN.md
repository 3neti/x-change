# Public Auto-Generate Plan

The follow-on Checkout and owner payments console work is specified in
[`CHECKOUT_PLAN.md`](CHECKOUT_PLAN.md) and governed by its companion compass.

Last updated: 2026-09-30

## Objective

Expose `/x/auto-generate` as a public Pay Code issuance surface. An
unauthenticated visitor may compose a permitted Pay Code, pay its complete
cost through On-Demand Issuance Funding, and receive the issued stamp. The
commissioned Commercial Principal remains the issuer of record.

For the first controlled proof:

- Public Auto-Generate is enabled by default;
- no Maker/Checker approval is required;
- no CAPTCHA is required yet;
- funding always uses `full_amount`;
- Bank Transfer is primary and fixed-amount QR Ph is secondary;
- the Commercial Principal is resolved only by the server; and
- authenticated Cockpit issuance remains unchanged.

This work reuses the authoritative instruction compiler, pricing, Funding
Intent, On-Demand Issuance Funding Order, Treasury hold, `GeneratePayCode`, and
stamp contracts. It must not create a parallel issuance engine.

## Identity and authority

The public visitor is not the institutional issuer and does not become an
x-change Account owner merely by paying.

```text
Public visitor
  -> requests and pays for one frozen instruction

Commercial Principal
  -> remains issuer and economic operating identity

System runtime
  -> executes the commissioned authority after verified payment
```

Every issued result must retain at least:

```yaml
issued_by: commercial_principal
executed_by: system_runtime
requested_by: guest_session_hash
funded_by: funding_intent_reference
funding_basis: full_amount
authorization: public_auto_generate
```

The browser must never supply or override the principal identifier, issuer
identifier, Account reference, Client Funds source, or System Principal.

## Default configuration

The first proof uses a default-enabled package setting with an immediate host
kill switch:

```php
'public_auto_generate' => [
    'enabled' => env('XCHANGE_PUBLIC_AUTO_GENERATE_ENABLED', true),
    'path' => '/x/auto-generate',
    'funding_basis' => 'full_amount',
],
```

Setting the environment variable to `false` disables new public order
creation. Existing frozen or paid orders continue through their governed
terminal or recovery paths; disabling the entry point must not strand money.

The route also fails closed when commissioning is incomplete or the active
Commercial Principal cannot be resolved unambiguously.

## Public surface

`/x/auto-generate` is a public Inertia route and does not use Cockpit
authentication or `CockpitLayout`. It uses the public claim/onboarding shell.

The visible journey is:

```text
Create your Pay Code
  -> Review and pay
  -> Payment verified
  -> Pay Code ready
  -> Open, copy, or share
```

The public page must not expose:

- the Cockpit sidebar or navigation;
- the Funding navigation link;
- Engineering Preview;
- Account or Commercial Principal balances;
- Client Funds controls;
- saved-template administration; or
- internal Treasury and provider diagnostics.

## Shared issuance workspace

Extract the reusable issuance experience from the existing Quick Generate
page:

```text
PayCodeIssuanceWorkspace
  |- Instruction Composer
  |- Pricing and cost summary
  |- Pay Code preview
  |- On-Demand Funding workspace
  `- Issued stamp/share result
```

Two server-authored surface profiles consume it:

| Profile | Shell | Issuer context | Funding |
| --- | --- | --- | --- |
| `cockpit` | Cockpit | Authenticated Account | Existing policy |
| `public_auto_generate` | Public | Commercial Principal | Forced `full_amount` |

The profile is an enforcement input, not merely a collection of Vue hiding
flags. Backend validation must reject Cockpit-only fields on the public
surface.

## Guest-order security

The page is public, but each order is possession-protected. Preparing an order
creates:

- a cryptographically random guest access token;
- only the token hash in durable storage;
- an opaque order reference;
- a binding to the browser session;
- the resolved Commercial Principal;
- frozen instructions and authoritative pricing; and
- an explicit expiry.

An order reference alone is never sufficient authorization. A visitor may
read, acknowledge, verify, cancel when safe, or resume only the order bound to
their session and access token.

Initial abuse controls remain intentionally small but mandatory:

- route and order-creation throttles;
- independent payment-check throttles;
- one active unpaid order per guest session;
- request idempotency; and
- order expiry and cleanup.

Turnstile is deferred until the commercial workflow is proven.

## Financial path

The public surface always uses `full_amount`:

```text
Verified guest payment
  -> Commercial Principal order-bound hold
  -> principal to Pay Code Reserve
  -> governed service and instruction fee positions
  -> GeneratePayCode
  -> issued stamp
```

The visitor does not receive an Account, a Bavix wallet, or Client Funds.
Wrong, late, duplicated, reversed, or ambiguous provider evidence follows the
On-Demand Issuance Gate 10 recovery rules.

## Controlled implementation gates

Implementation status as of 2026-09-30: Gates 1–7 and the rollback-only half
of Gate 8 have a package implementation and focused automated coverage. The
separately authorized testing-instance payment remains the final Gate 8
acceptance step. Gate 9 commissioning adoption and Gate 10 Turnstile remain
controlled future gates.

### Gate 1 — Shared workspace extraction

**Implemented.** Cockpit and public entry points consume the same Quick
Generate workspace and funding dialog. Layout and allowed controls come from a
server-authored surface profile; Cockpit behavior remains the default.

Extract the Composer, authoritative cost summary, funding workspace, and issued
stamp into reusable components. Preserve the current Cockpit behavior and its
tests before adding the public page.

### Gate 2 — Public page and surface profile

**Implemented.** `/x/auto-generate` renders without Cockpit navigation,
Funding navigation, the workspace switcher, Engineering Preview, or template
management.

Add the public Inertia page and layout. Hide Cockpit navigation, Funding,
Engineering Preview, balances, and administrative controls through the
server-authored `public_auto_generate` profile.

### Gate 3 — Commercial Principal resolver

**Implemented.** `CommercialPrincipalResolverContract` resolves the configured
active commissioned principal and fails closed unless the match is unique.

Introduce or strengthen `CommercialPrincipalResolverContract`. It must return
exactly one active commissioned Commercial Principal or fail closed. No public
request parameter may select the principal.

### Gate 4 — Guest access boundary

**Implemented for the proof.** The browser session holds the raw possession
token, while the order stores only its hash and the session hash. Public order
routes require both. The current active order is resumed instead of creating a
second unpaid order.

Add hashed guest access tokens, session binding, expiry, idempotency, and
owner-scoped public order routes. Prove that another browser session cannot
read or operate an order.

### Gate 5 — Public preparation

**Implemented.** Public submissions reuse the Quick Generate compiler and
pricing route, force `full_amount`, freeze the order, and return the existing
funding workspace.

Compile and price permitted Composer inputs through the existing backend
services, force `full_amount`, freeze one Issuance Funding Order, and return
the existing funding workspace. Do not issue or move money during preparation.

### Gate 6 — Payment and issuance

**Implemented by reuse.** Public orders use the existing exact-amount funding,
polling, hold, recovery, `GeneratePayCode`, stamp, and sharing contracts.

Reuse exact Bank Transfer, fixed-amount QR Ph, automatic verification,
single-use provider evidence, order-bound holds, and exactly-once issuance.
Replace the funding controls with the normal Pay Code stamp after issuance.

### Gate 7 — Public UX hardening

**Initial implementation complete.** The public shell uses purchase language
and restores the active order from the session. Browser acceptance remains in
Gate 8.

Use plain purchase language, preserve the visitor's draft after recoverable
errors, support refresh/resume, and keep internal accounting terminology out
of the public surface.

### Gate 8 — Package lifecycle scenario

**Rollback proof implemented.** The package-owned
`public_auto_generate_demo` scenario is executable through the command line
and an authenticated operator browser surface. It exercises the real public
preparation, funding, verification, hold, issuance, and stamp/share services
with the QR simulator inside an outer database transaction. The browser report
shows six steps and rolled-back artifacts; it calls no provider and confirms
that no Pay Code, order, funding record, observation, settlement, or wallet
value survives.

The canonical proof composes a ₱25.00 Pay Code with a selfie requirement. Its
current authoritative total is ₱43.00: ₱25.00 principal plus ₱18.00 in service
and instruction fees. The exact path is exercised end to end by the lifecycle
runner. Mismatched, duplicate, late, expired, and reversed evidence remain
covered by the shared On-Demand Issuance recovery suite used by the same
services.

Add a browser-based `public_auto_generate` lifecycle scenario proving public
access, server-side principal resolution, anti-spoofing, complete pricing,
guest isolation, exact funding, exactly-once issuance, recovery states, and
the stamp/share result. Simulation must roll back all value.

After simulation passes, conduct one separately authorized low-value payment
in the testing instance.

That payment is not authorized by running the rollback proof. Publication,
testing-host adoption, and the real payment require an explicit subsequent
gate.

### Gate 9 — Commissioning adoption

After the workflow is proven, make the feature a versioned commissioning
policy:

```yaml
public_issuance:
  enabled: true
  path: /x/auto-generate
  funding_basis: full_amount
  commercial_principal: primary
```

Commissioning and strict doctor validate, fingerprint, and report the policy.
Existing orders retain their policy snapshot when the setting changes.

### Gate 10 — Turnstile

Add `PublicRequestVerificationContract` and a Cloudflare Turnstile driver only
after the public issuance lifecycle is accepted. Validate once, server-side,
when the visitor asks to freeze a payable order. Page viewing, payment polling,
and result viewing remain challenge-free.

## Verification matrix

Automated and browser evidence must prove:

- the default-enabled route is available only after safe commissioning;
- the environment kill switch blocks new orders;
- Cockpit Quick Generate is unchanged;
- the public page has no sidebar, Funding link, or Engineering Preview;
- the server selects the Commercial Principal and rejects spoofed identity;
- public requests always use `full_amount`;
- principal and every fee appear in the authoritative total;
- a guest cannot access another guest's order;
- refresh resumes the same order;
- acknowledgement alone does not prove payment;
- exact provider evidence issues exactly one Pay Code;
- underpayment, excess, duplicate, late, and reversed evidence do not silently
  issue;
- no guest Account, wallet, or Client Funds position is created; and
- the result uses the existing stamp, claim link, and sharing contracts.

## Non-goals for the first proof

- CAPTCHA or Turnstile;
- Maker/Checker approval;
- campaign ownership;
- Pay Code-funded issuance;
- guest dashboards or transaction history;
- public saved-template management;
- guest Client Funds; and
- arbitrary principal selection.

## Deferred TODO — Curated Public Offerings catalog

Status: **deferred and not implemented**. The current public composer must
continue to start blank and must not expose private Cockpit templates.

After the blank public-issuance workflow is proven, consider a separately
commissioned catalog at `/x/auto-generate` that presents a small menu of
operator-approved products. Examples may include a simple cash Pay Code, a
Pay Code with fixed evidence requirements, or an approved collection or
insurance journey.

The catalog must observe these boundaries:

- offerings are created, reviewed, published, paused, and withdrawn only by
  authorized operators;
- public visitors cannot create, edit, save, or persist offerings;
- private Cockpit templates and their registry are never exposed;
- authoritative principal, pricing, instruction requirements, limits, expiry,
  and funding policy are fixed server-side in a versioned offering snapshot;
- selecting an offering prepares one one-time instruction through the existing
  public issuance and payment engine; and
- withdrawing an offering prevents new orders without changing already paid or
  issued orders.

This is a public product menu, not public template management. Implementation
requires its own authorization, audit, versioning, abuse-control, lifecycle,
and browser-acceptance gates.

## Release boundary

No push, tag, package publication, host adoption, Cloud deployment, provider
call, or real-money transfer is authorized by this plan.
