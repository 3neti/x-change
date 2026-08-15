# x-change

x-change is a Laravel package for building an institution-hosted financial
platform. It is a **Settlement Operating System** around Pay Codes,
provider-authoritative Account funding, Treasury positions, claims, campaigns,
and auditable delivery workflows.

Deployment and commissioning are package-owned. Start with
[Getting Started](./GETTING_STARTED.md), then use the
[X-Change Cloud Recipe](./docs/deployment/X_CHANGE_CLOUD_RECIPE.md) for a
reviewable Laravel Cloud plan, apply, ship, resume, and acceptance workflow.

It is designed for banks, electronic money issuers, regulated financial
operators, and application teams that need programmable settlement without
making a user-facing Account balance pretend to be a bank balance.

> **Release status:** public beta. Use controlled environments, explicit
> provider certification, and human-authorized small-value acceptance before
> production use.

## The model in one minute

A Pay Code is an externally visible bearer reference. It resolves to a Voucher,
which is the governed contract. The Voucher's instructions describe what may be
collected, verified, presented, and executed. Claim and execution workflows
then enforce those instructions through configured provider and application
capabilities.

```text
Pay Code
    -> Voucher and instructions
    -> Claim workflow
    -> Execution driver
    -> Provider, Account, delivery, and journal outcomes
```

A Pay Code does not itself hold money, mutate an Account, or authorize a bank
transfer. Those effects occur only through the applicable execution and
financial authority boundaries.

## What the package provides

### Cockpit

The authenticated Cockpit is the operating surface for an Account holder:

- **Create** designs, prices, previews, and issues Pay Codes.
- **Funding** presents QR Ph, bank-transfer, and funding Pay Code paths backed
  by authoritative evidence.
- **Pay Codes** searches, filters, inspects, shares, and follows Pay Code
  lifecycles.
- **Campaigns** imports beneficiary worksheets, obtains officer authorization,
  issues batches, exports results, and explicitly orchestrates delivery.
- **Accounts** manages provider-positioned Account configuration without
  exposing provider secrets.

The Cockpit uses cached, sanitized read models. Opening a page does not perform
a provider balance request or execute a financial operation.

The package also supplies the authenticated application navigation shell. A
pristine Laravel host gets one responsive sidebar for Cockpit workspaces,
Documentation, settings, and account controls; the starter Repository link and
the former nested Cockpit sidebar are not retained. Customized host shells are
detected and left untouched for deliberate integration.

### Claim and execution

Voucher instructions can compose:

- identity and input collection;
- mobile, OTP, KYC, location, selfie, and signature controls;
- Rider message, link, splash, and Stamp presentation;
- disbursement, payment, settlement, Account funding, campaign authorization,
  and onboarding outcomes;
- feedback delivery and post-claim actions.

The claim workflow is resolved from explicit Voucher and execution metadata.
Special workflows such as campaign officer authorization and onboarding do not
guess their behavior from route names or arbitrary form fields.

### Funding

Account funding is evidence-driven. Supported workflows include:

- reusable QR Ph Account funding addresses;
- exact-amount provider Funding Intents;
- bank-transfer instructions and transaction-history matching;
- controlled funding requests and Account-funding Pay Codes;
- webhook, operator-check, and scheduled verification through one idempotent
  verification pipeline.

A webhook permits intake of provider evidence. It does not independently
authorize Account credit. The configured provider history or equivalent
authoritative observation must confirm the destination, amount, currency, and
settlement state.

### Campaigns

Campaign worksheets support:

- CSV and XLSX import with mapping and staged validation;
- paste and drag-and-drop intake for simple beneficiary lists;
- common Voucher instructions for a batch;
- immutable worksheet freezing;
- zero-value Settlement Pay Code authorization by an authenticated officer;
- Pay Code distribution and direct-bank fulfillment planning;
- explicit SMS, email, and export actions;
- append-only delivery attempts, outcomes, and retries.

Issuing Pay Codes never automatically sends them. Delivery is a separate,
auditable operator action routed through the x-change feedback workflow.

## Financial authority model

x-change keeps three facts separate:

1. **Provider Inventory** — value authoritatively observed at a bank or EMI
   settlement resource.
2. **Treasury Positions** — internal attribution of that value to the System
   Account, Client Funds, reserves, provider costs, revenue, commissions, and
   other purposes.
3. **Pay Code obligations** — outstanding settlement instructions that reserve
   capacity without claiming that provider money has already moved.

An Account can hold positions across several providers and currencies. The
underlying ledger may use multi-wallet storage, but product code and user-facing
language use **Account**, **Client Funds**, **Provider Liquidity**, **Treasury
Position**, and **Issuance Capacity**.

Issuance Capacity cannot exceed the relevant Account position or fresh provider
liquidity and remains cognizant of outstanding Pay Code obligations.

Financial mutations are expected to be:

- provider-authoritative where a provider fact exists;
- atomic across Inventory and Account posting;
- idempotent under webhook, polling, operator, and queue retries;
- append-only in their journal and reconciliation evidence;
- fail-closed when topology, authority, or liquidity is unresolved.

Commercial charges are accepted as an immutable, versioned x-commerce sale and
posted through Commercial Clearing into provider-cost payables, product
revenue, attributed partner payables, and commercial revenue. Provider costs
and partner commissions leave Inventory only from exact settlement evidence;
partner payouts additionally require independent maker-checker approval.

Fresh commissioning activates immutable package baseline pricing without
fabricating human approval. Price changes remain locked until different named
maker and checker operators are authorized; approval and activation are
separate controls. See [Commercial Governance](./docs/commercial-governance/README.md).

Operators can verify the complete boundary without moving money:

```bash
php artisan x-change:treasury:attest-commercial-accounting --json --no-interaction
```

## Package architecture

x-change is the orchestration package. Its collaborators provide bounded
capabilities:

| Package area | Responsibility |
| --- | --- |
| `3neti/emi-core` and provider adapters | Provider-neutral capabilities, readiness, balances, funding evidence, QR instructions, payouts |
| `3neti/wallet` | System-principal resolution and Treasury-position storage contracts |
| `3neti/voucher`, `3neti/cash`, `3neti/instruction` | Governed Voucher and execution instruction model |
| `3neti/form-flow` and handlers | Compiled claim workflow and identity or evidence collection |
| `3neti/x-commerce` | Pricing, commercial waterfall, and allocation plans |
| `3neti/x-campaign` | Private worksheets, authorization, fulfillment, and delivery state |
| `3neti/x-feedback` | Dedicated SMS, email, and feedback delivery lifecycle |
| `3neti/x-journal` | Append-only operational and financial evidence |
| `3neti/x-action`, `3neti/x-ray`, `3neti/x-rider` | Actions, inspection, and recipient experience |
| `3neti/onboarding` | Mobile-first Account onboarding and identity handoff |

Provider packages contribute capabilities and connection templates. x-change
does not branch its public architecture on whether a provider is a large bank,
small bank, or EMI.

## Supported deployment profiles

Provider activation is explicit:

| Profile | Intended topology |
| --- | --- |
| `development` | Local simulation; rejected in production |
| `netbank` | `netbank-primary` |
| `paynamics` | `paynamics-primary` |
| `hybrid` | NetBank and Paynamics primary connections |
| `custom` | Explicit provider-contributed connection references |

Installing credentials or a provider package does not activate that provider.
The selected profile, connection configuration, and strict readiness checks do.

## Install in a new Laravel host

```bash
laravel new x-PayOut
cd x-PayOut
composer config minimum-stability beta
composer config prefer-stable true
composer require '3neti/x-change:^1.0@beta' -W
php artisan x-change:setup
```

`x-change:setup` is the guided local workflow. It configures the development
profile, safely adopts a conventional Laravel User model, installs the package,
builds the frontend, provisions the non-interactive System Account, and runs
strict verification.

Adopted hosts use a root Composer hook to refresh the package-owned build
inputs through `x-change:publish`; `x-change:doctor --assets --strict` verifies
the complete Form Flow, handler, Rider, X-Ray, and Cockpit publication catalog.
Configuration is never part of that automatic overwrite boundary.

For automation:

```bash
php artisan x-change:setup \
  --profile=development \
  --target=local \
  --write-env \
  --no-interaction
```

Start with [GETTING_STARTED.md](./GETTING_STARTED.md) for the full bank
developer, DevOps, provider-integration, and first-commissioning walkthrough.

## Commission and deploy

x-change starts fail-closed. Until commissioning is complete, protected web,
API, claim, authentication, and webhook traffic receives a neutral unavailable
response. `/up` remains the infrastructure liveness endpoint and `/x/ready`
reports x-change operational readiness.

The supported production sequence is:

```bash
php artisan x-change:configure --profile=netbank
php artisan x-change:doctor --pre-install --strict --no-interaction
php artisan x-change:commission --dry-run --json --no-interaction
php artisan x-change:commission --no-interaction
php artisan x-change:doctor --strict --no-interaction
```

`x-change:configure` updates a sanitized, managed block in `.env.example`; it
never writes production secrets. Deployment platforms own production
environment values.

The higher-level deployment interface is:

```bash
php artisan x-change:deploy production --plan
php artisan x-change:deploy production
```

See [DEPLOYMENT.md](./DEPLOYMENT.md) for deployment manifests, Laravel Cloud,
manual recovery, and safety gates.

## Runtime responsibilities

Local development normally runs:

```bash
php artisan queue:work database --queue=x-change-funding,x-change-feedback,default --sleep=3 --timeout=60
php artisan schedule:work
php artisan reverb:start # only when Reverb broadcasting is enabled
```

Production should use managed workers, a scheduler, shared cache locks, and the
selected broadcast service. The dedicated `x-change-feedback` queue must remain
separate from funding verification.

## Verification and development

Run focused package tests with an increased PHP memory limit:

```bash
php -d memory_limit=2G vendor/bin/pest
```

Run a non-live lifecycle scenario:

```bash
php artisan xchange:lifecycle:run basic_cash --json
```

Lifecycle scenarios that can call a live provider or move real money require
explicit runtime gates, confirmation flags, and stable run references. They are
acceptance tools, not seeders or balance-repair commands.

Do not edit package source in the host's `vendor/` directory. Follow the
[package development workflow](./docs/deployment/PACKAGE_DEVELOPMENT_WORKFLOW.md),
release an immutable package version, and update the host through Composer.

## Documentation

- [Partner API](docs/partner-api/README.md) — OAuth server-to-server integration, OpenAPI, Postman, and HTTP acceptance.
- [Partner API Compass](docs/architecture/PARTNER_API_COMPASS.md) — implemented boundary and deferred MCP/security gates.

- [Getting started](./GETTING_STARTED.md)
- [Deployment and commissioning](./DEPLOYMENT.md)
- [Thin-host configuration](./docs/deployment/THIN_HOST_CONFIGURATION.md)
- [Settlement OS compass](./docs/architecture/SETTLEMENT_OS_COMPASS.md)
- [Treasury initial state and Account portfolios](./docs/architecture/TREASURY_INITIAL_STATE_AND_ACCOUNT_PORTFOLIOS.md)
- [Execution engine compass](./docs/architecture/execution-engine/EXECUTION_ENGINE_COMPASS.md)
- [Cockpit home contract](./docs/ui-cockpit/COCKPIT_HOME_UI_UX.md)
- [Funding workspace contract](./docs/ui-cockpit/FUNDING_WORKSPACE_UI_UX.md)
- [Create Pay Code contract](./docs/ui-cockpit/CREATE_PAY_CODE_UI_UX.md)
- [Pay Code explorer contract](./docs/ui-cockpit/PAY_CODE_EXPLORER_UI_UX.md)
- [Campaign import contract](./docs/ui-cockpit/CAMPAIGN_WORKSHEET_IMPORT_UI_UX.md)

## License

See [LICENSE.md](./LICENSE.md) for the authoritative license terms.
