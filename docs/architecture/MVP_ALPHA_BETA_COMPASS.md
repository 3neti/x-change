# x-change MVP Alpha/Beta Compass

## Decision

The current package and host application are suitable for a **controlled
alpha** after the Alpha Gate below is completed.

They are **not yet suitable for an unattended fresh-cloud alpha or an external
beta**.

The settlement, funding, Pay Code, campaign, onboarding-voucher, journal, and
Cockpit foundations are substantial. The remaining launch risk is concentrated
in deployment bootstrap, identity provisioning, production operations, and the
first-use journey rather than the core product model.

## Product Promise For MVP

An Account holder must be able to:

1. arrive at an x-change landing page;
2. create or receive an Account invitation;
3. verify a mobile number and establish a PIN;
4. see the Cockpit rather than a legacy dashboard;
5. add Account funds through provider-authoritative evidence;
6. see Issuance Capacity;
7. create, inspect, share, and claim Pay Codes;
8. prepare and authorize a campaign;
9. receive SMS or email delivery evidence; and
10. recover from ordinary failures without an engineer changing balances.

## Identity Baseline

### Production

A normal production migration creates **zero users**.

`DatabaseSeeder` calls `TestingUserSeeder`, but that seeder is deliberately
restricted to `local` and `testing`. It seeds two local identities and throws
if it is run in production. It is not a production bootstrap mechanism.

The Treasury system principal must be a real, persisted, unique user before
Treasury provisioning. It is resolved through the wallet package's federated
system-user resolver using the configured x-change candidate. The resolver does
not create the user.

Therefore, a fresh production deployment currently needs a separate,
controlled system-principal creation step before Treasury provisioning. This
gap blocks unattended fresh-cloud installation.

### Local and testing

The host test seeder currently creates:

1. Lester Hurtado; and
2. Anaïs Santos.

These are test fixtures, not deployment identities.

## Current Entry And Onboarding Paths

### Self-registration

Fortify registration is enabled. A visitor can create an Account using:

- mobile number;
- optional name;
- optional email; and
- PIN plus PIN confirmation.

The new user is authenticated and sent to mobile verification. Production
mobile verification fails closed if a real OTP driver is not configured.

After verification, the current flow sends the user to Funding. However,
self-registration does not currently provision the same provider-positioned
Treasury Account portfolio created by the onboarding-voucher execution path.
This must be unified before self-service alpha.

### Invitation

An operator can author an onboarding-enabled Pay Code from Create or apply
onboarding to campaign beneficiaries. The onboarding execution driver:

- requires the configured identity fields;
- requires verified mobile evidence when policy demands it;
- resolves or creates the user;
- opens the local Account;
- provisions its Treasury positions;
- establishes first-PIN setup state; and
- schedules authenticated claimant handoff.

This is the correct architectural invitation primitive. MVP still needs an
obvious **Invite someone** entry point and delivery/status language around that
primitive.

## Navigation Baseline

The accepted authenticated home is `/x/cockpit`.

The host still contains legacy navigation:

- Fortify redirects successful login to `/x/dashboard`;
- `/dashboard` redirects to `/x/dashboard`;
- the public landing page links authenticated users to `/x/dashboard`;
- the public landing page is still the Laravel starter page; and
- host header components still link to `/x/dashboard`.

Alpha must make `/x/cockpit` canonical and redirect the legacy dashboard routes
to it. Users must not encounter two competing operating homes.

## Fresh-Cloud Target Sequence

```text
Deploy code
    ↓
Configure database, cache, queue, storage, mail, SMS, broadcasting
    ↓
Configure enabled provider connections
    ↓
Create exactly one production system principal
    ↓
Run migrations
    ↓
Run strict provider and operations preflight
    ↓
Provision and reconcile Treasury
    ↓
Optionally capitalize provider opening funds with explicit authority
    ↓
Publish/build assets and cache production configuration
    ↓
Start web, queue workers, scheduler, and optional Reverb
    ↓
Run browser and lifecycle smoke acceptance
    ↓
Open registration or distribute onboarding invitations
```

No step accepts an operator-entered provider balance.

## Alpha Gate

Every item is required before inviting real alpha users.

### P0 — Deployment and system principal

- [ ] Add a guarded production system-principal bootstrap command or seeder.
- [ ] Require a stable identifier, mobile, email, and explicit confirmation.
- [ ] Make retries idempotent and fail if multiple candidates resolve.
- [ ] Establish the supported fresh-cloud command order.
- [ ] Prove install from an empty production-like database.
- [ ] Prove a second install changes neither balances nor Treasury topology.
- [ ] Add a strict readiness command whose non-zero exit status blocks deploy.
- [ ] Keep `--no-treasury` an explicit deferred mode, never an automatic
      provider-failure fallback.

### P0 — Canonical product entry

- [ ] Replace the Laravel starter landing page with an x-change landing page.
- [ ] Send authenticated visitors to `/x/cockpit`.
- [ ] Change Fortify's successful-login home to `/x/cockpit`.
- [ ] Redirect `/dashboard` and `/x/dashboard` to `/x/cockpit`.
- [ ] Remove legacy Dashboard links from host navigation.
- [ ] Browser-test guest, newly registered, invited, and returning-user entry.

### P0 — Account onboarding

- [ ] Make self-registration and onboarding-voucher execution converge on one
      Account provisioning service.
- [ ] Provision the local Account and Treasury positions exactly once.
- [ ] Require production OTP delivery and verification.
- [ ] Preserve first-PIN setup for invitation-created Accounts.
- [ ] Add a clear **Invite someone** action backed by an onboarding Pay Code.
- [ ] Allow invitation delivery by canonical claim URL through the existing
      x-change feedback workflow.
- [ ] Show invitation status without exposing identity or provider payloads.
- [ ] Prove duplicate mobile/email conflicts fail closed.

### P0 — Provider authority

- [ ] Explicitly choose each connection mode: `required`, `optional`, or
      `disabled`.
- [ ] Enable NetBank only after live authentication, balance-read, funding
      history, QR/VCA, webhook, and payout checks pass.
- [ ] Keep Paynamics disabled until its intended alpha capabilities pass the
      same provider-specific certification.
- [ ] If both are enabled, prove separate Inventory and Position
      reconciliation for each connection.
- [ ] Prove provider unavailability cannot create, credit, or transfer value.
- [ ] Prove repeated webhook, polling, and operator checks remain idempotent.
- [ ] Complete a human-authorized small-value live funding and payout UAT.

### P0 — Funding first-use journey

- [ ] A zero-fund user sees Funding as the recommended first action.
- [ ] QR Ph and bank-transfer instructions identify the Account without using
      payer mobile as a routing key.
- [ ] Provider evidence, not form input, authorizes Account credit.
- [ ] Client Funds and Issuance Capacity refresh after authoritative posting.
- [ ] Pending, suspense, reversal, and stale-liquidity states have usable copy.
- [ ] No ordinary user sees system liquidity or Treasury-only controls.

### P0 — Queue, scheduler, and delivery operations

- [ ] Use a durable production queue and shared cache.
- [ ] Run workers for `default`, `x-change-feedback`, and `x-change-funding`.
- [ ] Give provider-facing jobs explicit timeouts shorter than `retry_after`.
- [ ] Record terminal failure evidence for every externally dependent job.
- [ ] Run the Laravel scheduler continuously.
- [ ] Use `onOneServer()` or an equivalent shared lock for multi-node schedules.
- [ ] Configure Resend and prove email delivery.
- [ ] Configure EngageSpark or the approved SMS transport and prove SMS
      delivery.
- [ ] Configure a production OTP driver separately from feedback delivery.
- [ ] Confirm campaign issuance never sends merely because Pay Codes exist.

### P0 — Storage and artifacts

- [ ] Use durable shared object storage for funding evidence, Rider Splash
      snapshots, and immutable Stamp artifacts.
- [ ] Do not use an ephemeral instance-local disk in a multi-instance cloud
      environment.
- [ ] Enforce private evidence access and public claim-artifact boundaries.
- [ ] Back up the database and retained artifacts.

### P0 — Security and operational safety

- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`, and a stable `APP_KEY`.
- [ ] Use HTTPS and secure cookies.
- [ ] Configure trusted hosts/proxies and webhook IP/signature policy.
- [ ] Keep secrets only in the cloud secret store.
- [ ] Define maker/checker identities independently of ordinary Account users.
- [ ] Confirm System Treasury-only facts are authorization-gated.
- [ ] Monitor failed jobs, provider failures, suspense, reconciliation drift,
      and webhook rejection.
- [ ] Establish rollback, provider-disable, and incident-response procedures.

### P0 — Acceptance

- [ ] Fresh-cloud install completes without test fixtures.
- [ ] System principal and all enabled Treasury connections reconcile.
- [ ] Guest registration → OTP → Account provisioning → Cockpit passes.
- [ ] Onboarding invitation → claim → PIN setup → Cockpit passes.
- [ ] QR Ph funding and bank-transfer funding each credit exactly once.
- [ ] A Pay Code can be issued, shared, claimed, and journaled.
- [ ] A campaign can be imported, authorized by another officer, fulfilled,
      delivered explicitly, and exported.
- [ ] Mobile and desktop browser checks pass without console errors.
- [ ] Package assets match the published host assets.
- [ ] Focused package tests and the production frontend build pass.

## Beta Gate

Beta begins only after the Alpha Gate has operated successfully with a small,
named cohort.

- [ ] Replace configured user-ID control lists with first-class roles and
      permissions.
- [ ] Add operator UI for invitation, maker/checker review, suspense, failed
      delivery, and reconciliation queues.
- [ ] Add immutable administrative audit for role and configuration changes.
- [ ] Complete provider certification and failure drills.
- [ ] Load-test campaign import, issuance, delivery, and claim traffic.
- [ ] Prove multi-node queue, scheduler, cache, Reverb, and storage behavior.
- [ ] Define retention, privacy, data-export, and deletion policies.
- [ ] Complete legal/regulatory copy and operational review.
- [ ] Add customer support and incident escalation procedures.
- [ ] Establish observable service-level targets and alerting.
- [ ] Run backup restoration and provider failover exercises.

## Production Configuration Families

The exact values belong in the deployment secret store and must never be
copied into documentation or source control.

### Application

- `APP_*`
- database connection
- session and cache stores
- filesystem/object-storage disk
- queue connection
- trusted proxy/host configuration

### System and Treasury

- stable system-user identifier and identifier column
- legal entity, principal, mandate, legal profile, and version
- connection modes and opening policies
- explicit opening-capitalization authority when applicable

### NetBank

- client credentials and authentication endpoints
- corporate/source Account identifiers
- customer and sender identifiers
- funding/VCA alias settings
- standing-address HMAC key and key ID
- QR merchant and endpoint settings
- webhook allowlist/authentication settings

### Paynamics

- merchant credentials and endpoints
- provider-account and KYC/link settings
- explicit live-request enablement
- Treasury connection mode and opening policy

### Messaging and authentication

- Resend/mail credentials and sender identity
- x-feedback SMS driver, sender, and EngageSpark credentials
- OTP driver and its provider credentials
- feedback and funding queue names

### Real time

- broadcasting connection
- Reverb application credentials and public Vite settings
- production WebSocket host, scheme, and port

## Release Labels

Use these labels consistently:

- **Scaffolded** — code or UI exists but the lifecycle is not accepted.
- **Alpha-ready** — the Alpha Gate passes in a production-like environment.
- **Controlled alpha** — named users operate with active monitoring and manual
  escalation.
- **Beta-ready** — the Beta Gate passes and ordinary external users may
  participate under published operating procedures.
- **Production-ready** — a separate legal, security, provider, accounting, and
  operational sign-off has been completed.

The current state is: **controlled-alpha candidate; Alpha Gate incomplete**.
