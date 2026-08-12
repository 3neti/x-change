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

Fresh production deployment now has a guarded package-owned bootstrap:

```bash
php artisan x-change:system-principal:provision \
    --authorization-reference=deployment:<stable-reference> \
    --commit \
    --confirm-system-principal \
    --no-interaction
```

The command previews by default, requires the configured stable email
identifier, generates no usable credential, opens the System Account exactly
once, rejects ambiguous identities, and refuses a different authorization
reference on retry. `x-change:install` can invoke the same operation before
Treasury provisioning with `--provision-system-principal`.

The system principal deliberately has no interactive mobile identity. It is a
Treasury principal, not an operator login; maker/checker work belongs to named
human officers.

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

After verification, the current flow sends the user to Funding.
Self-registration and onboarding-voucher execution now converge on the same
idempotent Account provisioner. Mobile verification is committed only if the
local Account and its provider-positioned Treasury portfolio are ready.

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
obvious delivery/status language around that primitive. The Cockpit now exposes
**Invite**, which opens Quick Generate with a first-class Invitation mode:
onboarding identity requirements already enabled, a durable mode selector that
survives Blank/Repeat Last/template selection, and a truthful "Account
Invitation" classification in place of the ordinary Pay Code label. It does
not introduce a second invitation route or execution path, and claiming an
invitation still grants no operator authority.

## Navigation Baseline

The accepted authenticated home is `/x/cockpit`. `/x/dashboard` is now a
compatibility redirect, Fortify uses the Cockpit as its successful-login home,
and the mobile-first auth publish set includes the x-change landing page.

The host may still contain legacy links while migrating:

- `/dashboard` may redirect through `/x/dashboard`; and
- older host header components may still link to `/x/dashboard`.

Both paths finish in the Cockpit; the legacy x-change Dashboard component is no
longer rendered.

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

- [x] Add a guarded production system-principal bootstrap command or seeder.
- [x] Require a stable configured email identifier, deployment authorization,
      and explicit ownership confirmation.
- [x] Keep the system principal non-interactive rather than assigning it a
      recoverable mobile login.
- [x] Make retries idempotent and fail if multiple candidates resolve.
- [ ] Establish the supported fresh-cloud command order.
- [ ] Prove install from an empty production-like database.
- [ ] Prove a second install changes neither balances nor Treasury topology.
- [x] Add a strict readiness command whose non-zero exit status blocks deploy.
- [ ] Keep `--no-treasury` an explicit deferred mode, never an automatic
      provider-failure fallback.

### P0 — Canonical product entry

- [x] Replace the Laravel starter landing page with an x-change landing page.
- [x] Send authenticated visitors to `/x/cockpit`.
- [x] Change Fortify's successful-login home to `/x/cockpit`.
- [x] Redirect `/dashboard` and `/x/dashboard` to `/x/cockpit`, allowing the
      host compatibility route to pass through `/x/dashboard`.
- [ ] Remove legacy Dashboard links from host navigation.
- [ ] Browser-test guest, newly registered, invited, and returning-user entry.

### P0 — Account onboarding

- [x] Make self-registration and onboarding-voucher execution converge on one
      Account provisioning service.
- [x] Provision the local Account and Treasury positions exactly once.
- [x] Require production OTP delivery and verification.
- [ ] Preserve first-PIN setup for invitation-created Accounts.
- [x] Add a clear **Invite someone** action backed by an onboarding Pay Code.
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
- [x] Give provider-facing jobs explicit timeouts shorter than `retry_after`.
- [x] Record terminal failure evidence for every externally dependent job.
- [ ] Run the Laravel scheduler continuously.
- [x] Use `onOneServer()` or an equivalent shared lock for multi-node schedules.
- [ ] Configure Resend and prove email delivery.
- [ ] Configure EngageSpark or the approved SMS transport and prove SMS
      delivery.
- [ ] Configure a production OTP driver separately from feedback delivery.
- [ ] Confirm campaign issuance never sends merely because Pay Codes exist.

### P0 — Storage and artifacts

- [ ] Use durable shared object storage for funding evidence, Rider Splash
      snapshots, and immutable Stamp artifacts.
- [ ] Set `XCHANGE_CLAIM_SHARE_ARTIFACT_DISK` to a durable shared object-store
      disk in every Cloud or multi-instance deployment; the default `local`
      disk is acceptable only for a single-node local development environment.
- [ ] Teach commissioning and `x-change:doctor --strict` to reject an
      instance-local Claim Share artifact disk in Cloud/production profiles.
- [ ] Backfill or rematerialize persisted Stamp descriptors whose immutable
      bytes are absent, then verify their SHA-256, MIME type, and 1200 × 630
      dimensions before advertising the content-addressed URL again.
- [ ] Prove across a deploy and instance replacement that an issued Pay Code's
      Open Graph image remains byte-identical and publicly readable.
- [ ] Do not use an ephemeral instance-local disk in a multi-instance cloud
      environment.
- [ ] Enforce private evidence access and public claim-artifact boundaries.
- [ ] Back up the database and retained artifacts.

### P0 — Security and operational safety

- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`, and a stable `APP_KEY`
      (strict readiness enforcement implemented; deployment evidence pending).
- [ ] Use HTTPS and secure cookies (strict readiness enforcement implemented;
      deployment evidence pending).
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

## Commissioning Mode

A fresh host is intentionally unavailable for financial use until the package
records a successful installation manifest. Commissioning is package-owned and
does not use Laravel maintenance mode.

| State | Meaning | Public behavior |
| --- | --- | --- |
| `configuration_required` | Required deployment settings are absent or invalid | Neutral `503`; no setting names or credentials |
| `ready_to_install` | Static configuration passes but package migrations have not established the manifest boundary | Neutral `503` |
| `installation_incomplete` | Installation began, was interrupted, or its sanitized configuration fingerprint changed | Neutral `503` |
| `operational` | Manifest, profile, active connections, and current fingerprint agree | Normal application traffic |

The gate covers host web routes, authentication, Cockpit, claims, payments,
package APIs, lifecycle APIs, and provider webhooks. Static files are served by
the web server. `/up` remains a process liveness signal and `/x/ready` is the
financial application readiness signal.

The public commissioning screen is asset-independent and identifies no
provider, account, secret, or missing variable. A protected read-only checklist
uses `XCHANGE_COMMISSIONING_ACCESS_TOKEN`; it reports sanitized checks and
server commands but cannot edit `.env` or submit credentials. All commissioning
responses use `no-store`, `noindex`, and `Retry-After` headers. An unconfigured
`APP_ENV=local` installation also accepts the known development PIN `317537`.
That fallback is deliberately unavailable in production, staging, testing, and
other environments; live deployments must never run with `APP_ENV=local` and
must configure a strong, unique commissioning token.

The checklist distinguishes configured services from observed processes. Local
development uses the named `x-change-funding`, `x-change-feedback`, and
`default` queues, `schedule:work`, and optional `reverb:start`. Laravel Cloud
and Forge use their managed queue, minutely scheduler, and optional WebSocket
integrations instead of long-running commands in the web process.

The package scheduler also runs
`xchange:treasury:refresh-liquidity` every five minutes. It reads the provider
and updates only the cached liquidity guard; it never posts Inventory or
Positions. A stale snapshot is labelled `Stale` in the Cockpit header and makes
Issuance Capacity unavailable until a successful refresh.

Installation writes the manifest only after migrations, system-principal and
Treasury work, opening reconciliation/capitalization, and required publication
finish successfully. `--no-treasury` deliberately leaves commissioning
incomplete. Provider downtime after a valid manifest is operational degradation
and does not return the deployment to Commissioning Mode.

## Deployment Readiness Gate

Human diagnostics remain non-blocking:

```bash
php artisan x-change:doctor --json --no-interaction
```

Deployment automation must use strict mode:

```bash
php artisan x-change:doctor --strict --json --no-interaction
```

The strict command returns a non-zero status when any reported check fails. Its
versioned JSON report includes the overall result, strict-mode state, pass/fail
counts, and the individual checks. A deploy must stop on that non-zero status;
it must not retry with `--no-treasury` or silently disable a provider.
