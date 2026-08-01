# Getting started with x-change

This guide is for the application developers, bank or EMI integration teams,
DevOps engineers, and AI agents responsible for bringing an x-change host from
a new Laravel application to a commissioned environment.

Use this document for the first successful adoption. Use
[DEPLOYMENT.md](./DEPLOYMENT.md) for routine deployment, recovery, and command
details.

## What you are installing

`3neti/x-change` is the orchestration package for an institution-hosted
Settlement Operating System. The Laravel application is the deployment host;
the package owns the Pay Code, funding, claim, Treasury, campaign, and Cockpit
workflows.

The normal adoption path is:

```text
New Laravel host
    -> install 3neti/x-change
    -> choose a provider profile
    -> configure the deployment environment
    -> run fail-closed commissioning
    -> operate workers, scheduler, and optional broadcasting
```

Installing a provider package does not activate it. The deployment profile and
runtime environment explicitly select the active connections.

## Who owns what

| Team | Primary responsibility |
| --- | --- |
| Application developers | Laravel host, product customization, package upgrades, automated tests |
| Bank or EMI integration team | Provider endpoints, credentials, account identifiers, supported rails, webhooks, sandbox certification |
| DevOps | Secret injection, database, cache, queues, scheduler, WebSockets, deployment, observability, backups |
| Treasury or operations | System Account authorization, opening reconciliation, controlled live acceptance, exception review |

No team should send credentials through source control, issue trackers, chat,
test fixtures, screenshots, or command output.

## Prerequisites

Before starting, confirm:

- PHP 8.3 or newer and Composer are available;
- Node.js and npm are available for the Cockpit build;
- a supported Laravel application can connect to its database and cache;
- the selected provider package is available through Composer;
- production credentials can be stored in the deployment platform's secret
  store;
- the bank or EMI has identified separate sandbox and production endpoints and
  credentials.

Laravel Cloud, Forge, containers, and conventional servers are valid hosts.
Production requires durable shared infrastructure rather than local files or
in-memory process state.

## Fast local evaluation

Create a normal Laravel application. Host display names begin with `x-` and use
title case; technical slugs use lowercase kebab case.

```bash
laravel new x-PayOut
cd x-PayOut
composer config minimum-stability beta
composer config prefer-stable true
composer require '3neti/x-change:^1.0@beta' -W
php artisan x-change:setup
```

The interactive setup command chooses the development profile, safely adopts a
conventional Laravel User model, prepares local configuration with consent,
installs package resources, builds the frontend, provisions the System Account,
and verifies the result.

For repeatable local automation:

```bash
php artisan x-change:setup \
  --profile=development \
  --target=local \
  --write-env \
  --no-interaction
```

Preview the same workflow without changing files or state:

```bash
php artisan x-change:setup --dry-run --json --no-interaction
```

Finish by checking readiness:

```bash
php artisan x-change:doctor --strict --no-interaction
```

The development setup may temporarily disable onboarding OTP and the invited
user's initial PIN-setup step. Production commissioning rejects those relaxed
settings. Ordinary login still requires an established credential.

The same setup command safely adopts a pristine Laravel application shell. It
replaces the starter Dashboard, Repository, and framework-documentation links
with the responsive Cockpit navigation, while retaining the host's account
menu, settings, and logout controls. An unknown customized sidebar is never
overwritten automatically; integrate it manually or explicitly publish the
`x-change-shell` tag.

To inspect or repeat only the host adoption step:

```bash
php artisan x-change:host:adopt --dry-run --json
php artisan x-change:host:adopt
```

## Provider profiles

Choose one profile intentionally:

| Profile | Connections | Use |
| --- | --- | --- |
| `development` | No live provider | Local simulator and UI evaluation only |
| `netbank` | `netbank-primary` | NetBank deployment |
| `paynamics` | `paynamics-primary` | Paynamics deployment |
| `hybrid` | Both primary connections | NetBank and Paynamics together |
| `custom` | Explicit contributed references | Additional or multiple institutional connections |

The `development` profile is rejected in production. A provider can contribute
several connections, currencies, rails, and custody modes without introducing
bank-specific behavior into the x-change public interface.

## Prepare a bank or EMI environment

Generate the sanitized environment checklist for the selected profile:

```bash
php artisan x-change:configure --profile=netbank
```

This updates only the marked x-change section of `.env.example`. It does not
write `.env`, copy runtime credentials, or reveal secret values.

The bank or EMI integration team should supply, as applicable:

- OAuth or API authentication endpoints and credentials;
- balance, transaction-history, status, disbursement, and QR endpoints;
- corporate account and virtual-account identifiers;
- supported currencies and settlement rails;
- QR merchant and alias configuration;
- webhook source authentication and delivery requirements;
- provider limits, settlement statuses, and sandbox test cases.

Use the exact variable names generated for the selected profile. Provider
packages own those requirements; do not maintain a separate handwritten list
that can drift from the installed adapter.

Inspect the resolved contract after DevOps supplies the values:

```bash
php artisan x-change:configuration:inspect --strict
php artisan x-change:doctor --pre-install --strict --no-interaction
```

The pre-install check must pass before migrations, Treasury initialization, or
live provider calls are authorized.

## Core production configuration

The production secret store must define the generated provider variables and
the core deployment identity, including:

```dotenv
XCHANGE_DEPLOYMENT_PROFILE=netbank
XCHANGE_SYSTEM_USER_COLUMN=email
XCHANGE_SYSTEM_USER_ID=system@example.test
XCHANGE_TREASURY_LEGAL_ENTITY_REFERENCE=legal-entity:example
XCHANGE_TREASURY_LEGAL_PROFILE_VERSION=2026-01
```

Use a deployment-specific System Account email and stable legal-entity
reference. The System Account is non-interactive and must not be an employee's
ordinary login.

Production onboarding should retain:

```dotenv
XCHANGE_MOBILE_VERIFICATION_ENABLED=true
XCHANGE_ONBOARDING_REQUIRE_OTP=true
XCHANGE_ONBOARDING_REQUIRE_PIN_SETUP=true
XCHANGE_MOBILE_VERIFICATION_SHOW_LOCAL_CODE=false
```

New local and testing installations default onboarding OTP and mobile
verification to disabled so the interface can be evaluated before an SMS OTP
driver is configured. Production commissioning remains fail-closed and
requires the controls shown above, a live OTP driver, and hidden local
verification codes.

Configure a live OTP driver and the selected email and SMS transports before
enabling those delivery channels. Disabled delivery capabilities should remain
visibly disabled; they must not silently fall back to a different queue or
transport.

## Commission the environment

Review the remote plan first:

```bash
php artisan x-change:commission --dry-run --json --no-interaction
```

Then commission the configured environment:

```bash
php artisan x-change:commission --no-interaction
```

Commissioning performs:

1. strict pre-install diagnostics;
2. idempotent package installation;
3. System Account provisioning;
4. live Treasury preflight and opening reconciliation;
5. commissioning-manifest recording;
6. strict operational diagnostics.

It stops on missing credentials, unreachable providers, invalid topology,
unsafe production settings, unresolved opening balances, or missing runtime
infrastructure.

Opening provider funds are not automatically declared to belong to the System
Account. System capitalization is a separate controlled decision requiring an
ownership confirmation and an auditable authorization reference.

## Runtime processes

For local development, keep the required workers and scheduler running:

```bash
php artisan queue:work database --queue=x-change-funding,x-change-feedback,default --sleep=3 --timeout=60
php artisan schedule:work
```

Start broadcasting only when enabled:

```bash
php artisan reverb:start
```

In Laravel Cloud or Forge, configure equivalent managed processes instead of
running development commands in an interactive shell. At minimum, monitor:

- funding verification jobs;
- feedback and campaign delivery jobs;
- default application jobs;
- scheduler execution and overlap locks;
- failed jobs and retry outcomes;
- Reverb or the selected broadcast service when live Cockpit updates are
  enabled.

The dedicated `x-change-feedback` queue is part of the delivery boundary. SMS
and email workflows must not be rerouted through the funding queue.

## Financial authority boundaries

These rules apply to every provider integration:

- A webhook permits evidence intake; it does not itself authorize Account
  credit.
- Provider history or another authoritative provider observation confirms the
  transaction.
- Inventory recognition and Account posting occur atomically and idempotently.
- A page load does not call the provider; the Cockpit reads cached projections.
- Provider liquidity must reconcile with Inventory and Treasury Positions before
  issuance capacity is trusted.
- Manual repair and capitalization require explicit evidence and append-only
  audit records.
- Live lifecycle scenarios require explicit gates and must never infer consent
  to move real money.

## First go-live acceptance

Perform acceptance in increasing order of risk:

1. Run strict diagnostics and confirm the commissioning page reports ready.
2. Confirm the System Account and its Account exist exactly once.
3. Confirm database, cache, queues, scheduler, and optional broadcasting are
   healthy.
4. Confirm the provider balance observation is current and agrees with internal
   Inventory and Treasury Positions.
5. Exercise registration and onboarding with a test mobile and live OTP.
6. Create and claim a non-live or zero-value test Pay Code.
7. Verify email and SMS through the dedicated feedback workflow.
8. With Treasury and provider operators present, run one separately authorized,
   small-value live provider test.
9. Confirm one provider transaction, one Inventory movement, one Account
   movement, and complete journal evidence.
10. Confirm retrying the same reference does not repeat the financial movement.

Do not use a successful HTTP response as financial acceptance. Provider,
Inventory, Position, Account, and journal evidence must agree.

## Handoff checklist

Before the bank integration team hands the environment to operations, record:

- selected deployment profile and active connection references;
- credential owner and rotation process, without recording secret values;
- certified provider endpoints and supported capabilities;
- source-account and destination-account ownership controls;
- webhook authentication and network allow-list decisions;
- provider status mapping and pending-settlement policy;
- reconciliation owner and exception-escalation path;
- queue, scheduler, broadcast, and failed-job monitoring owners;
- evidence for the controlled live acceptance transaction;
- rollback and incident contacts.

## Upgrades and source changes

Do not edit package code inside the host's `vendor/` directory. Develop package
changes in a separate clone, test and tag the package, then update the host with
Composer.

After an upgrade:

```bash
composer update 3neti/x-change -W
php artisan x-change:install --no-interaction
php artisan x-change:doctor --strict --no-interaction
npm run build
```

See [Package development workflow](./docs/deployment/PACKAGE_DEVELOPMENT_WORKFLOW.md)
for the local-clone, path-repository, release, and AI-agent workflow.

## Where to go next

- [Deployment and recovery runbook](./DEPLOYMENT.md)
- [Thin-host configuration](./docs/deployment/THIN_HOST_CONFIGURATION.md)
- [Package dependency matrix](./docs/deployment/X_CHANGE_PACKAGE_DEPENDENCY_MATRIX.md)
- [Bank sandbox validation](./docs/operations/bank-sandbox-validation.md)
- [Pre-deployment checks](./docs/operations/pre-deployment-checks.md)
- [Settlement OS compass](./docs/architecture/SETTLEMENT_OS_COMPASS.md)
