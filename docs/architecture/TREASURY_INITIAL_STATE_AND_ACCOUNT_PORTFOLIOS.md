# Treasury Initial State and Account Portfolios

## Status

This document describes the implemented provider-neutral Treasury foundation for x-change. It covers system initialization, NetBank and Paynamics connections, Account portfolios, verified funding, opening reconciliation, legacy cutover, and safe local simulation.

The operator product language is intentionally **Account**, **Internal Balance**, **Provider Liquidity**, **Issuance Capacity**, **Account Funding**, and **Treasury Position**. Database columns and third-party APIs may still use `wallet` where that is the provider or ledger's technical term.

x-legal supplies advisory, versioned vocabulary when installed. Its current Philippine profile is under analysis and is not an execution authority. No legal profile, label, or UI state can authorize money movement.

## Core model

x-change does not equate an Account balance with a bank balance. It maintains three related but distinct facts:

1. **Provider Inventory** — the authoritative value observed at a bank or EMI settlement resource.
2. **Treasury Positions** — the internal attribution of that value to the system or to an Account.
3. **Pay Code obligations** — outstanding settlement instructions that reduce issuance capacity without pretending that provider money has already moved.

For each enabled provider connection, the system principal owns:

- a **Treasury Clearing Position** for verified value waiting to be attributed; and
- a **Legacy Unattributed Position** for authoritative opening value whose Account ownership has not yet been established;
- an **Account Funding Reserve Position** for reconciled opening value that has been explicitly confirmed as system-owned;
- a **Commercial Clearing Position** for accepted commercial charges waiting to be allocated; and
- separate provider-cost payable, product revenue, partner commission payable, royalty payable, tax payable, and commercial revenue Positions.

Each Account owns one **Client Funds Position** for every active provider connection and currency. An Account can therefore have NetBank and Paynamics positions at the same time without creating a separate application user for each provider.

```text
System principal
├── netbank-primary / PHP
│   ├── Treasury Clearing
│   ├── Legacy Unattributed
│   ├── Account Funding Reserve
│   ├── Commercial Clearing
│   └── Commercial payable and revenue Positions
└── paynamics-primary / PHP
    ├── Treasury Clearing
    ├── Legacy Unattributed
    ├── Account Funding Reserve
    ├── Commercial Clearing
    └── Commercial payable and revenue Positions

Account principal
├── netbank-primary / PHP / Client Funds
└── paynamics-primary / PHP / Client Funds
```

The underlying ledger can use Bavix multi-wallet storage, but application code depends on Treasury contracts and purpose-bound positions rather than wallet names.

## Provider boundary

`3neti/emi-core` defines the provider capability kernel. A provider advertises a capability manifest and supplies independent adapters for the capabilities it implements, including:

- readiness probing;
- authoritative balance reading;
- funding evidence reading;
- funding instruction issuance; and
- payout execution where supported.

NetBank and Paynamics register their own manifests, readiness probes, and authoritative balance readers. Adding another bank or EMI requires a provider package to implement the same contracts and an x-change connection entry; it does not require a new Account-balance algorithm.

Connection modes are:

- `required` — installation and preflight fail closed if the provider is unavailable;
- `optional` — an unavailable provider is reported and skipped; or
- `disabled` — the connection is not part of the active Treasury.

## System principal

`3neti/wallet` owns `SystemUserResolverContract`. Its resolver accepts one or more named candidates and fails closed when:

- no candidate resolves;
- a candidate is malformed;
- a query is ambiguous;
- the model lacks ledger capability; or
- candidates resolve to different principals.

The legacy `SYSTEM_USER_ID` and `ACCOUNT_SYSTEM_USER_MODEL` settings remain a compatibility fallback. Production deployments should publish `account.php` and configure a stable, unique system-principal candidate. The record must exist before Treasury provisioning begins.

The system principal is an application identity, not a different identity for every bank. Provider separation is represented by connection-scoped Treasury Positions.

## First-deployment sequence

Configure the legal entity, system principal, and provider connections before running the installer:

```dotenv
XCHANGE_TREASURY_LEGAL_ENTITY_REFERENCE=entity:example-ph
XCHANGE_TREASURY_SYSTEM_PRINCIPAL_REFERENCE=principal:system
XCHANGE_TREASURY_SYSTEM_MANDATE_REFERENCE=mandate:system:treasury
XCHANGE_TREASURY_LEGAL_PROFILE=treasury-settlement-ph-v1
XCHANGE_TREASURY_LEGAL_PROFILE_VERSION=2026-07-24.1

XCHANGE_TREASURY_NETBANK_MODE=required
XCHANGE_TREASURY_PAYNAMICS_MODE=disabled
```

Then run:

```bash
php artisan x-change:install --no-interaction
```

The default installer:

1. validates static configuration and the durable Treasury identity;
2. classifies each active Treasury connection from its durable local topology;
3. runs a read-only live provider preflight only for a fresh or safely resumable
   connection;
4. stops before migrations, Treasury Positions, or UI publication when a
   required fresh or resumable connection fails;
5. publishes migration prerequisites and runs migrations;
6. resolves the system principal and idempotently provisions zero-balance
   Treasury Positions only for fresh or resumable connections that passed live
   preflight;
7. reads authoritative provider balances again for their one-time opening
   reconciliation;
8. applies an explicitly authorized opening capitalization policy, when
   requested; and
9. publishes the UI and remaining host assets only after initialization
   succeeds.

### Installer lifecycle classification

`x-change:install` is both a first-deployment command and an idempotent package
republish command. It must not treat an operating Treasury as though it were
opening again.

Before provider access, every active connection is assigned one lifecycle
state:

- **fresh or resumable** — the canonical topology is absent, or only an exact,
  non-conflicting subset of the ten system Treasury Positions exists and the
  provider Inventory has not yet been registered. The installer runs the
  fail-closed live preflight, idempotently completes provisioning, and performs
  opening reconciliation.
- **initialized** — all ten canonical active system Treasury Positions match
  the configured principal, mandate, provider, connection, currency,
  settlement resource, custody mode, legal profile, and reconciliation
  reference, and the matching active provider Inventory exists. The installer
  does not call the provider, provision Positions, reconcile an opening
  balance, or run opening capitalization for that connection.
- **incomplete or conflicting** — an existing Position or Inventory conflicts
  with the immutable configured topology, or an Inventory exists without the
  complete canonical Position set. The installer fails before migrations,
  Treasury writes, or UI publication. This state requires an explicit
  accounting or configuration repair; it is never guessed into another state.

The Inventory is the important completion boundary. Position provisioning can
be interrupted and resumed safely, while opening reconciliation registers the
Inventory even when the authoritative opening balance is zero. Balance amounts
and operation history are deliberately not used as initialization markers.

For an initialized connection, the expected reinstall message is:

```text
Treasury already initialized [netbank-primary]; skipping opening live preflight and reconciliation.
```

This separation makes asset republishing deterministic during provider downtime
and prevents an operating balance difference from being reinterpreted as
opening capital.

### Explicit disposable fresh-database mode

For a disposable non-production host that intentionally shares a development
provider account, use the destructive mode explicitly:

```bash
php artisan x-change:install \
    --fresh-database \
    --confirm-database-reset \
    --seeder='Database\Seeders\DatabaseSeeder' \
    --force \
    --no-interaction
```

This is not the routine reinstall path. `--fresh-database` is limited to
`local` and `testing`, cannot be combined with `--no-migrate` or
`--no-treasury`, and does nothing unless `--confirm-database-reset` and an
explicit host bootstrap `--seeder` are supplied. Existing `--force` is not
destructive consent; it continues to mean published-file replacement only.

The fresh-database sequence is deliberately fail-closed:

1. validate destructive options, Treasury configuration, opening policy, and
   the seeder class;
2. run the read-only live provider preflight for every active connection;
3. stop without changing the database, running a seeder, or publishing assets
   if a required connection fails;
4. publish migration prerequisites and run `migrate:fresh --force`;
5. run only the explicitly named bootstrap seeder;
6. reject a seeder that pre-creates or conflicts with Treasury topology;
7. provision the ten zero-balance system Positions and reconcile the current
   provider balance as a new opening snapshot; and
8. publish the UI and remaining assets only after Treasury succeeds.

Use a dedicated bootstrap seeder that creates required identities, including
the configured system principal, but does not fund an Account or create
Treasury balances. The generic `DatabaseSeeder` is acceptable only when its
contents satisfy that constraint.

With the default `unattributed` opening policy, a successful fresh snapshot has
these balances:

```text
Provider liquidity = Treasury Inventory = total Treasury Positions
Legacy Unattributed = Provider liquidity
Account Funding Reserve = 0
Every other system Treasury Position = 0
Every system or user Account = 0, unless the explicit bootstrap seeder funded it
```

The provider balance does not become the system user's Account balance.
Recognizing provider liquidity and allocating Account value are separate
accounting actions. Capitalization or Account allocation still requires its
own evidence and authorization.

A fresh snapshot only restores equality at that instant. If another deployment
later uses the same provider account, this Treasury instance will drift again.
The production rule remains: **one provider corporate account belongs to one
Treasury instance**. Disposable database resets are a temporary development
workflow, not a shared-account reconciliation strategy.

`--force` controls replacement of published files only. It never weakens
Treasury configuration, topology validation, ownership confirmation, or the
first-deployment live readiness and reconciliation controls. It does not force
an initialized connection through the opening workflow. `--no-interaction`
never prompts, invents an opening amount, silently skips a required fresh
connection, or falls back to manual capitalization.

Static and live readiness are deliberately separate. Static readiness verifies
installed provider capabilities and required configuration names. Live
readiness verifies DNS/TLS transport, provider authentication, the balance
endpoint, and the authoritative response shape. It is read-only: it may obtain
an access token and read a balance, but it does not register an account, create
a transfer, or move money.

Opening reconciliation deliberately attributes a new provider balance to
`Legacy Unattributed`. It does not assume that the entire bank or EMI balance
belongs to the system. To confirm that ownership and make the exact reconciled
value available for system-issued Account Funding Pay Codes, use the guarded
opening policy:

```bash
php artisan x-change:install \
    --force \
    --treasury-opening-policy=system-capital \
    --capitalization-authorization-reference=deployment-20260726-001 \
    --confirm-system-ownership \
    --no-interaction
```

The installer first reconciles the provider, then moves the complete
`Legacy Unattributed` amount to `Account Funding Reserve`. It accepts no amount
from the operator. A deterministic operation reference makes retries
idempotent.

The opening policies are:

- `unattributed` — the safe default; keep opening value unattributed;
- `system-capital` — capitalize every active provider connection after the
  explicit ownership confirmation; and
- `configured` — apply the per-connection policy, allowing NetBank,
  Paynamics, and future providers to differ.

Equivalent configuration is:

```dotenv
XCHANGE_TREASURY_OPENING_POLICY=unattributed
XCHANGE_TREASURY_NETBANK_OPENING_POLICY=unattributed
XCHANGE_TREASURY_PAYNAMICS_OPENING_POLICY=unattributed
XCHANGE_TREASURY_OPENING_CAPITALIZATION_ALLOW_PRODUCTION=false
XCHANGE_TREASURY_OPENING_CAPITALIZATION_ALLOWED_CONNECTIONS=netbank-primary
```

Production capitalization stays disabled unless
`XCHANGE_TREASURY_OPENING_CAPITALIZATION_ALLOW_PRODUCTION=true`. Connection
allowlisting is optional but recommended.

`XCHANGE_TREASURY_LEGAL_ENTITY_REFERENCE` must be an explicit, stable identifier for the deployment's legal entity, such as `legal-entity:example-ph`. Do not derive it from `APP_NAME` or silently default it: the reference is persisted with Treasury Position metadata and must remain stable across deployments.

`XCHANGE_TREASURY_LEGAL_PROFILE_VERSION` must also be explicitly pinned. It is part of the immutable Treasury Position definition, so a package upgrade must not silently advance it. Changing an existing deployment's profile version requires a controlled accounting migration; it is not an installer retry mechanism.

After changing environment configuration, run `php artisan optimize:clear` before retrying installation.

Use `--no-treasury` only for build or recovery workflows where Treasury
initialization is intentionally deferred. The installer prints that deferred
state visibly and does not run provider preflight, create Treasury Positions,
or perform opening reconciliation. It is not an automatic fallback after a
provider failure.

The component commands remain available:

```bash
php artisan x-change:treasury:preflight --no-interaction
php artisan x-change:treasury:preflight --live --no-interaction
php artisan x-change:treasury:provision --no-interaction
php artisan x-change:treasury:reconcile-opening --no-interaction
php artisan x-change:treasury:capitalize-opening \
    --connection=netbank-primary \
    --json \
    --no-interaction
```

The capitalization command is preview-only without `--commit`. A committed
standalone run additionally requires `--authorization-reference` and
`--confirm-system-ownership`. All Treasury commands accept
`--connection=<reference>`; operational commands support `--json` where
machine-readable output is useful. Preflight JSON reports `level`,
`static_ready`, `live_ready`, combined `ready`, and sanitized `issues`.

A failed required connection makes live preflight and installation fail closed.
This applies to a fresh or resumable connection that still requires opening
initialization. A failed optional connection remains visible but does not block
healthy required connections, and no Treasury Position is provisioned for that
optional connection. An initialized connection is not live-probed by the
installer; provider availability and balance drift are operational health
concerns, not package-installation gates.

Provider transport and response failures use only this sanitized taxonomy:

- `dns_resolution_failed`
- `connection_timeout`
- `tls_failure`
- `authentication_failed`
- `balance_endpoint_rejected`
- `invalid_balance_response`
- `provider_unavailable`

These results never contain credentials, access tokens, account numbers,
authorization headers, provider response bodies, or sensitive provider URLs.

## Opening reconciliation

Opening reconciliation reads provider balances through `ProviderBalanceReader`. It never accepts an operator-entered amount.

For one provider connection:

```text
authoritative provider balance
          │
          ▼
compare Provider Inventory with total Treasury Positions
          │
          ├─ equal ───────────────────────→ reconciled
          │
          ├─ provider is higher,
          │  Inventory equals Positions ─→ recognize exact delta into
          │                                Inventory + Legacy Unattributed
          │
          └─ provider is lower or
             internal ledgers disagree ──→ review_required; no automatic debit
```

This is deliberately asymmetric. An authoritative increase can be recognized without guessing its owner. A shortage, reversal, or inconsistent internal ledger can never be repaired by silently removing client value.

Provider observation, inventory recognition, and position recognition use stable operation references and database uniqueness constraints. Re-running reconciliation does not recognize the same difference twice.

## Opening system capitalization

Opening capitalization is an attribution change, not a provider deposit and
not an Inventory change:

```text
Provider Inventory (unchanged)
          │
          └── total Treasury Positions (unchanged)
                 Legacy Unattributed
                         │ exact full amount
                         ▼
                 Account Funding Reserve
```

The command re-reads authoritative provider liquidity and requires:

- Provider Inventory equals the provider observation;
- the sum of active Positions equals Provider Inventory;
- exactly one Legacy Unattributed Position;
- exactly one Account Funding Reserve Position; and
- an empty reserve unless the deterministic capitalization operation already
  exists.

A shortage, ambiguity, partial amount, changed authorization input, or
unrecognized reserve balance fails closed. The authorization reference and a
hash of provider evidence are stored; credentials, raw evidence, and provider
response bodies are not.

## Account onboarding

Account onboarding provisions a portfolio after provider preflight:

```text
Account
  ├─ Client Funds / netbank-primary / PHP
  └─ Client Funds / paynamics-primary / PHP
```

Position references are derived from the immutable Account principal reference, provider, connection, currency, and purpose. Re-running onboarding resolves the same positions rather than creating duplicates.

Provider account settings in Cockpit may be shared or dedicated:

- **Shared provider account** uses the platform's configured settlement resource.
- **Dedicated provider account** stores an Account-specific provider reference and remains blocked until authoritative ownership verification succeeds.

Credentials are not displayed in Cockpit and are not part of the Account balance read model.

## Verified funding flow

Funding credit follows one accounting pipeline regardless of whether verification was triggered by webhook intake, an operator check, or scheduled polling:

```text
provider observation
      │
      ▼
verify provider, destination, amount, currency, and uniqueness
      │
      ▼
recognize Provider Inventory
      │
      ▼
recognize Treasury Clearing Position
      │
      ▼
allocate Clearing → Account Client Funds Position
      │
      ▼
refresh Internal Balance and Issuance Capacity
```

The webhook authenticates intake; it does not authorize credit. Provider history is the settlement authority. A payer mobile number is evidence only and is never an Account-routing key.

Every provider observation, inventory recognition, position recognition, and allocation has a durable idempotency reference. Repeated webhook delivery, repeated **Check NetBank**, and concurrent scheduled polling converge on one Account credit.

Large deposits use the same rule. An operator does not type “₱2,000,000” into an Account. The system first observes the exact amount at the configured provider resource. If destination evidence identifies the Account, the verified funding pipeline allocates it. If ownership is unknown, it remains unattributed until a separately controlled reconciliation process proves the destination.

## Balance meanings

- **Internal Balance** is the sum of active Client Funds Positions for the Account and currency.
- **Provider Liquidity** is the latest authoritative or safely cached provider observation.
- **Outstanding Pay Codes** is the current active Pay Code liability.
- **Issuance Capacity** is:

```text
max(0, min(Client Funds, Provider Liquidity - Outstanding Pay Codes))
```

Client Funds already excludes value moved into Pay Code Reserve. The formula therefore subtracts the reserve from provider liquidity, not from Client Funds a second time. Capacity cannot exceed either spendable attributed funds or provider liquidity remaining after the outstanding obligation.

## Commercial Offering and Pay Code waterfall

`3neti/x-commerce` is the canonical commercial-computation authority. One immutable **Commercial Offering** binds four versioned contracts: the Price List, Waterfall, Attribution Policy, and Legal Trace. Estimate screens, accepted sales, and lifecycle reports resolve the same offering; x-change never assembles those contracts independently at posting time.

The system principal administers offerings at `/x/cockpit/commercial`. A maker may prepare and submit a new version, but a different checker must publish it with an authorization reference. Published versions are immutable. `XCHANGE_COMMERCIAL_USE_PUBLISHED_OFFERINGS=false` keeps package bootstrap pricing active during adoption; switching it to `true` activates the currently effective approved offering.

The waterfall supports ordered fixed or percentage rules, optional floors and caps, and one exact residual:

```text
successful Pay Code issuance
          │
          ▼
immutable x-commerce sale snapshot
          │
          ▼
Account Client Funds → system Commercial Clearing
          │
          ├─→ Provider Cost Payable
          ├─→ Product Revenue
          ├─→ Partner Commission Payable (only for an attributed eligible participant)
          └─→ Commercial Revenue residual
```

The accepted snapshot contains the complete Commercial Offering, quote lines, exact allocation plan, provider, connection, rail, currency, product, recognition policy, expected provider cost, and actual attributed participants. x-change persists that versioned context before posting the Treasury movements. It does not recompute old sales from current configuration.

One database transaction creates the sale, charges Client Funds, and allocates every waterfall leg. Any failed leg rolls back the entire sale. The acceptance event and Treasury operation references are unique, so a repeated issuance handoff returns the original posting without a second debit. A reversal creates exact compensating Treasury movements; it never edits or deletes the original sale or ledger operations.

Provider cost payable is a commercial classification, not proof that a bank has already deducted cash. It is discharged only when exact provider, connection, currency, and amount evidence proves an external cash movement. Invoices or unmatched observations remain pending or under review and do not reduce Inventory.

Partner commission payable belongs to the actual attributed partner principal, not a generic system bucket. A commission rule is applied only when the sale carries a participant whose role is eligible under the snapshotted Attribution Policy. Without one, the rule is skipped and its value remains for the residual; x-change never invents a house partner to manufacture commission. Commission payout requires a maker request, a different checker, and exact settlement evidence. The resulting Position and Inventory reductions are atomic and idempotent.

The Commercial Controls workspace exposes the active Price List and Waterfall, immutable versions, recent sales, allocations, provider-cost state, earned commission state, and the Legal Trace. Access is capability-based and granted explicitly with `x-change:commercial:authorize-operator`; ordinary Account holders cannot inspect system commercial accounting.

`XCHANGE_COMMERCIAL_LEGAL_ENFORCEMENT=advisory` preserves the package legal trace when `3neti/x-legal` is not installed. `required` fails publication closed unless x-legal evaluates the offering as allowed. Legal decisions are snapshotted references, not mutable prose attached after a sale.

Ordinary Pay Code cancellation or expiry releases unclaimed principal from Pay Code Reserve to Client Funds. It does not refund an already accepted issuance charge. A full commercial reversal is limited to a failed issuance or an explicit administrative void before provider-cost settlement or partner-payout control has begun.

Every accepted sale, charge, allocation, evidence outcome, partner control, settlement, and reversal writes an idempotent x-journal event in the same database transaction as its accounting state. Inspect the invariant without calling a provider:

```bash
php artisan x-change:treasury:attest-commercial-accounting --json --no-interaction
```

For legacy sales, preview reconstructible journal gaps first:

```bash
php artisan x-change:treasury:backfill-commercial-accounting-journal \
    --sale=<commercial-sale-reference> \
    --json \
    --no-interaction
```

Appending the safe subset requires `--commit` and a stable `--authorization-reference`. The command never rewrites immutable sale snapshots, invents provider evidence, or fabricates a missing reversal reason.

Taxes, royalties, invoice collection, and additional provider-topology policies remain separately gated extensions. They must add versioned policy contracts and cannot mutate prior history.

The posting boundary is controlled by `XCHANGE_COMMERCIAL_WATERFALL_ENABLED`. Keep it disabled while upgrading an existing installation until the migrations have run and `xchange:treasury:provision` has created exactly one active provider connection with all commercial Positions. Enabling it makes a missing or ambiguous connection a hard issuance failure; x-change will not silently fall back to unclassified revenue accounting.

## Legacy Account cutover

Opening provider value is first reconciled into Legacy Unattributed. Only then may an existing Account balance be migrated:

```bash
php artisan x-change:treasury:migrate-legacy-account \
    <owner-primary-key> \
    --connection=netbank-primary \
    --commit \
    --no-interaction
```

The command accepts no amount. It reads the exact legacy balance, verifies sufficient provider-reconciled unattributed value, allocates that value to the Account's Client Funds Position, and removes the old ledger balance in one transaction. Its operation reference makes replay a no-op.

If the provider-reconciled unattributed balance is insufficient, the command fails without changing either ledger.

## Local deposit simulation

Local and testing environments may simulate an authoritative provider increase:

```bash
php artisan x-change:treasury:simulate-deposit \
    netbank-primary \
    100000000 \
    --reference=uat:netbank:one-million-pesos \
    --commit \
    --no-interaction
```

The amount is in minor units, so `100000000` is PHP 1,000,000.00.

The simulator is disabled in production, requires an allowed environment, requires `--commit`, and reuses the opening-reconciliation pipeline. A stable `--reference` makes retries idempotent. It creates unattributed system value; it does not arbitrarily credit an Account.

## Security invariants

- No form, command, webhook body, or operator can directly set Internal Balance.
- Raw provider payloads, credentials, full account numbers, QR payloads, and provider transaction identifiers are excluded from general Cockpit read models.
- Required providers fail closed when capability or credential preflight fails.
- Optional providers cannot weaken a required connection.
- Provider balances and currencies must match the configured connection.
- Provider shortages and ledger mismatches enter review; they are never auto-debited.
- All recognition and allocation operations are atomic, append-only, and idempotent.
- Suspense reconciliation remains a controlled workflow; checking a provider is not a manual-credit permission.
- x-legal remains advisory and cannot authorize an accounting operation.

## Current compatibility boundary

This wave makes Treasury Positions authoritative for funding recognition, Account balance reads, provider-specific funding policy, and Cockpit capacity. Existing technical API names and legacy lifecycle diagnostics may still say `wallet` for backward compatibility.

The live Treasury lifecycle now pilots provider-aware Pay Code accounting. Each enabled Account portfolio has a Client Funds Position and a Pay Code Reserve Position per provider connection. Before the scenario issues a Pay Code, it atomically reserves the beneficiary principal from Client Funds. A successful payout derecognizes that principal-only reserve and reduces the matching Provider Inventory by the same principal amount.

The configured provider rail fee is informational unless authoritative provider evidence shows that the provider deducted it from the controlled account. NetBank's observed payout flow moves only the beneficiary principal. The sender's system charge is a separate economic leg and must never be posted as NetBank cash movement.

The canonical Pay Code liability and existing execution ledger remain a compatibility read model. Non-zero sender commercial charges debit the provider-specific Client Funds Position into Commercial Clearing and post the accepted x-commerce waterfall. Pay Code principal reservation, terminal release, and provider-confirmed claim settlement are Treasury-position based and idempotent.

Post-transfer balance checks are deliberately observational. They never recognize a positive difference because the provider may briefly return a stale pre-payout balance. A positive difference is `provider_sync_pending`; rerunning the same lifecycle reference checks again without repeating the transfer. A provider balance below internal attribution, or any Inventory/Position mismatch, remains `review_required`.

## Accounted live basic_cash lifecycle

`treasury_live_basic_cash` is the live, durable counterpart of the rollback-only `treasury_basic_cash` scenario. It:

1. queries the configured NetBank connection through `ProviderBalanceReader`;
2. reconciles the authoritative bank amount into Provider Inventory and system Positions;
3. provisions the issuer's NetBank Client Funds and Pay Code Reserve Positions;
4. captures provider, Inventory, system, Account, legacy compatibility, and Pay Code liability balances;
5. reserves the full ₱150 beneficiary principal before issuing one canonical open-slice Pay Code;
6. submits three ordered claims for ₱75, ₱50, and ₱25 through the canonical x-change withdrawal pipeline;
7. enforces ten seconds before the second and third live claims;
8. posts each successful slice as its own Pay Code Reserve derecognition and Provider Inventory outflow;
9. observes the provider balance without recognizing stale positive differences; and
10. stores the sanitized result under a hashed, caller-supplied run reference.

For an existing Account funded before Treasury Positions became authoritative, migrate and backfill its verified standing-funding history first:

```bash
php artisan migrate --no-interaction

php artisan x-change:treasury:backfill-standing-funding-positions \
    <account-owner-id> \
    --connection=netbank-primary \
    --json

php artisan x-change:treasury:backfill-standing-funding-positions \
    <account-owner-id> \
    --connection=netbank-primary \
    --commit \
    --json
```

The first backfill call is a dry run. The committed call accepts only provider-observed funding that already has exact Inventory recognition and the original legacy Account credit. It allocates the amount once, recovers only the duplicate compatibility credit, and records stable operation references.

The scenario can move real money. One successful run submits three provider transfers totalling ₱150, not one transfer. It is disabled outside its configured environments and requires all three operator controls:

```bash
php artisan xchange:lifecycle:run \
    treasury_live_basic_cash \
    --issuer=<account-owner-id> \
    --live-provider \
    --confirm-live-transfer \
    --run-reference=<stable-change-or-uat-reference> \
    --json
```

The run reference is never stored in plaintext. It is HMAC-hashed and bound to the scenario, issuer, provider, amount, and currency. Reusing the same reference returns the durable result and makes no second provider transfer. Reusing it with different money-movement parameters is rejected.

`--run-reference` is intentionally caller-supplied and mandatory for this live-money scenario. If it is absent, the command exits before Pay Code issuance, provider submission, or Treasury mutation. It must not silently generate a reference and continue.

This differs from rollback-only scenarios such as `basic_cash`, where an automatically generated execution identity cannot create an irreversible external payment. For a live provider call, automatic generation would destroy retry safety: the provider could accept a payout while the command times out before recording the response, and a retry could generate a different reference and submit a duplicate payout.

Operationally:

- rerun the exact reference to retrieve the durable result or recheck a pending provider balance;
- treat a new reference as an explicit authorization for a new real-money transfer; and
- never create a new reference merely because output was delayed, interrupted, or ambiguous.

Any future reference-generation convenience must be a two-step preparation flow. It may persist and print a reference, but it must exit without contacting the provider. A separate, explicit command using that same reference and `--confirm-live-transfer` may then execute the payment.

The command reports:

- `provider_observation.balance_minor` — the amount returned by the authoritative provider balance reader;
- `inventory.balance_minor` — x-change's control total for the provider resource;
- `system_positions` — Clearing and Legacy Unattributed attribution owned by the system principal;
- `account_positions` — the issuer's provider-specific Client Funds balance, including `not_provisioned` for absent positions;
- `account_positions.by_purpose.pay_code_reserve` — the beneficiary principal held for the in-flight provider transfer;
- `legacy_compatibility_balance_minor` — the existing Pay Code escrow and fee ledger balance;
- `liability` — outstanding, redeemed, expired, and cancelled Pay Code amounts;
- `claims` — the ordered ₱75, ₱50, and ₱25 claim ledger, sanitized execution result, Treasury settlement, and accounting checkpoint for each slice;
- `accounting.after_claims` — the three complete Treasury snapshots showing Pay Code Reserve and Inventory after each provider transfer;
- `treasury_settlement.settlements` — the three Position derecognition and Inventory adjustment pairs tied back to the single ₱150 issuance reservation;
- `treasury_settlement` totals — ₱150 beneficiary principal and Provider Inventory outflow, any provider-evidenced rail-fee context, and the separately classified sender system charge;
- Pay Code issuance and claim state; and
- a sanitized payout reconciliation with no credentials, raw provider payload, full account number, or claimant mobile.

For the current NetBank contract, each slice derecognizes only the amount sent to the beneficiary. The scenario does not invent a bank fee when the slice reconciliation has none. The ₱15 issuance charge remains a separate compatibility/commercial fact and is not added to Provider Inventory outflow. Moving that charge into explicit Treasury commercial Positions belongs to the later accounting wave.

`provider_transfer_succeeded=true` with `accounting_status=provider_sync_pending` means the payout and internal Treasury posting completed but NetBank has not yet returned the reduced balance. Rerun the exact same command with the exact same run reference. The scenario observes NetBank again and never repeats the payout.

`provider_transfer_succeeded=true` with `accounting_status=review_required` means an internal invariant failed or the provider balance is below internal attribution. This is an accounting escalation, not permission to submit another run reference. The durable run is closed specifically to prevent duplicate payment.

### Historical missing-disbursement posting repair

A provider balance can be below internal Treasury attribution when an older, already-settled system-owned disbursement reached the provider but predates the append-only Treasury outflow posting. Opening reconciliation remains fail-closed and never guesses that this is the cause.

This is operational drift, not evidence that installation is incomplete. A
routine reinstall must not call opening reconciliation or attempt to repair the
difference. In particular, do not use repeated `x-change:install --force` runs
or opening capitalization to resolve
`provider-balance-below-internal-attribution`.

Check provider availability independently:

```bash
php artisan x-change:treasury:preflight --live --no-interaction
```

A sanitized `connection_timeout` means the provider could not be observed at
that moment. It does not invalidate a complete local Treasury topology and does
not block an asset-republish install.

First run the dedicated inspection command:

```bash
php artisan x-change:treasury:repair-missing-disbursement-postings \
    --connection=netbank-primary \
    --json \
    --no-interaction
```

Inspection is the default and performs no writes. A repair is eligible only when all of these controls agree:

- the provider reports a balance below balanced Inventory and Positions;
- successful `redeem` reconciliations have unique settled provider evidence, an exact principal amount and currency, and a provider timestamp after opening recognition;
- each candidate Pay Code belongs to the system principal;
- no matching Inventory or Position posting already exists;
- candidate principal totals exactly equal the provider-to-Treasury deficit; and
- the system's Legacy Unattributed Position can cover that exact principal total.

If inspection returns `status=rejected` with
`No authoritative missing disbursement postings explain the Treasury deficit.`,
no safe repair exists from the evidence currently available. Do not fabricate
reconciliation IDs, force a write-off, or seed another opening balance. Compare
the provider statement and authoritative settled transactions with append-only
Inventory and Position operations, then resolve the missing evidence or use an
approved accounting correction workflow.

Review the returned reconciliation IDs, then repeat them explicitly:

```bash
php artisan x-change:treasury:repair-missing-disbursement-postings \
    --connection=netbank-primary \
    --reconciliation=212 \
    --reconciliation=213 \
    --reconciliation=214 \
    --commit \
    --json \
    --no-interaction
```

The committed path reacquires the opening-reconciliation lock, reads the provider again, locks the Treasury control records, and revalidates every fact in one database transaction. It appends one principal-only Inventory adjustment and one Legacy Unattributed derecognition per reconciliation. It never debits Client Funds, never posts a configured fee, never exposes raw provider evidence, and rolls back the complete repair if any write or final control check fails. Deterministic operation references make an exact replay return `already_repaired` without another balance change.

### Legacy fee-boundary repair

Runs created before the principal-only boundary may have derecognized the beneficiary principal plus the configured rail fee even though NetBank deducted only the principal. Inspect and repair a known durable run by its stored run-record reference:

```bash
php artisan x-change:treasury:correct-pay-code-fee-posting \
    <durable-run-record-reference> \
    --json \
    --no-interaction

php artisan x-change:treasury:correct-pay-code-fee-posting \
    <durable-run-record-reference> \
    --commit \
    --json \
    --no-interaction
```

The first command is a dry run. The committed command is fail-closed and append-only: it accepts only a successful legacy live run whose old Position and Inventory operations equal principal plus fee and whose provider observation differs by that exact fee. It restores Inventory, recognizes the correction through Treasury Clearing, allocates it back to the issuer's Client Funds Position, and records deterministic operation references. Repeating the command returns `already_corrected` without changing any balance.

Configuration:

```dotenv
XCHANGE_LIFECYCLE_TREASURY_LIVE_BASIC_CASH_ENABLED=true
XCHANGE_LIFECYCLE_TREASURY_LIVE_BASIC_CASH_ENVIRONMENTS=local,staging
XCHANGE_LIFECYCLE_ALLOW_LIVE_PROVIDER_SCENARIOS=true
```

Provider credentials and destination configuration remain provider-package concerns and must not be placed in the run reference.

## Deployment checklist

1. Create one immutable system-principal record.
2. Configure stable system-principal candidate resolution.
3. Set the Treasury legal entity and versioned advisory profile.
4. Mark every installed provider connection `required`, `optional`, or `disabled`.
5. Configure provider credentials and settlement resources outside source control.
6. Run Treasury preflight.
7. Run `x-change:install` or provision and reconcile explicitly.
8. Review every `review_required` result before onboarding Accounts.
9. Provision Account portfolios.
10. Backfill verified historical funding before running a live payout for a migrated Account.
11. Reconcile legacy provider value before migrating any remaining legacy Account balance.
12. Keep the simulator disabled outside local and testing environments.
13. Do not enable non-zero production instruction fees until position-backed charge allocation is implemented and accepted.
