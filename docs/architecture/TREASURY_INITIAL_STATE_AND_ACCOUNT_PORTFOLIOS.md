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
- a **Commercial Clearing Position** for accepted commercial charges waiting to be allocated; and
- separate provider-cost payable, product revenue, partner commission payable, royalty payable, tax payable, and commercial revenue Positions.

Each Account owns one **Client Funds Position** for every active provider connection and currency. An Account can therefore have NetBank and Paynamics positions at the same time without creating a separate application user for each provider.

```text
System principal
├── netbank-primary / PHP
│   ├── Treasury Clearing
│   ├── Legacy Unattributed
│   ├── Commercial Clearing
│   └── Commercial payable and revenue Positions
└── paynamics-primary / PHP
    ├── Treasury Clearing
    ├── Legacy Unattributed
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

The installer:

1. publishes package assets and migrations;
2. runs migrations;
3. runs provider preflight as part of Treasury provisioning;
4. resolves the system principal;
5. idempotently provisions zero-balance Clearing and Legacy Unattributed Positions; and
6. reads authoritative provider balances and performs opening reconciliation.

Use `--no-treasury` only for build or recovery workflows where Treasury initialization is intentionally deferred.

The component commands remain available:

```bash
php artisan x-change:treasury:preflight --no-interaction
php artisan x-change:treasury:provision --no-interaction
php artisan x-change:treasury:reconcile-opening --no-interaction
```

All accept `--connection=<reference>`; all operational commands support `--json` where machine-readable output is useful.

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
max(0, min(Internal Balance, Provider Liquidity) - Outstanding Pay Codes)
```

Capacity therefore cannot exceed either attributed client funds or provider liquidity, and it is reduced by outstanding Pay Codes.

## Pay Code commercial waterfall

`3neti/x-commerce` is the canonical price and waterfall-calculation authority. Its versioned Pay Code catalog prices the selected instruction, input, validation, feedback, and rider items in integer minor units. Both estimate screens and accepted sales call the same quote engine.

The first implemented policy uses ordered fixed deductions and allocations followed by one exact residual:

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
          ├─→ Partner Commission Payable
          └─→ Commercial Revenue residual
```

The accepted snapshot contains the catalog version, policy version, attribution version, quote lines, and exact allocation plan. x-change persists that snapshot before posting the Treasury movements. It does not recompute old sales from current configuration.

One database transaction creates the sale, charges Client Funds, and allocates every waterfall leg. Any failed leg rolls back the entire sale. The acceptance event and Treasury operation references are unique, so a repeated issuance handoff returns the original posting without a second debit. A reversal creates exact compensating Treasury movements; it never edits or deletes the original sale or ledger operations.

Provider cost payable is a commercial classification, not proof that a bank has already deducted cash. Actual external settlement remains subject to provider evidence and reconciliation. Partner commission payable records the amount and recipient reference produced by the accepted attribution snapshot; a later controlled payout workflow is still required to discharge that payable.

Percentage rules, caps, taxes, royalties, partner payment, invoice collection, and provider-wallet topology posting remain separately gated extensions. They must add versioned policy contracts and cannot mutate version 1 history.

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

The canonical Pay Code liability and existing execution ledger remain as a compatibility mirror during this pilot. Non-zero sender commercial charges now debit the provider-specific Client Funds Position into Commercial Clearing and post the accepted x-commerce waterfall. Pay Code principal reservation, release, and settlement must still be wired into every production issue, cancel, expire, and claim path before the pilot becomes the system-wide accounting boundary.

Post-transfer balance checks are deliberately observational. They never recognize a positive difference because the provider may briefly return a stale pre-payout balance. A positive difference is `provider_sync_pending`; rerunning the same lifecycle reference checks again without repeating the transfer. A provider balance below internal attribution, or any Inventory/Position mismatch, remains `review_required`.

## Accounted live basic_cash lifecycle

`treasury_live_basic_cash` is the live, durable counterpart of the rollback-only `treasury_basic_cash` scenario. It:

1. queries the configured NetBank connection through `ProviderBalanceReader`;
2. reconciles the authoritative bank amount into Provider Inventory and system Positions;
3. provisions the issuer's NetBank Client Funds and Pay Code Reserve Positions;
4. captures provider, Inventory, system, Account, legacy compatibility, and Pay Code liability balances;
5. reserves the exact beneficiary principal before issuing one canonical `basic_cash` Pay Code;
6. claims it through `x_change_live_cash` and the configured payout provider;
7. posts the successful beneficiary principal as the Position and Inventory outflow;
8. observes the provider balance without recognizing stale positive differences; and
9. stores the sanitized result under a hashed, caller-supplied run reference.

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

The scenario can move real money. It is disabled outside its configured environments and requires all three operator controls:

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

The command reports:

- `provider_observation.balance_minor` — the amount returned by the authoritative provider balance reader;
- `inventory.balance_minor` — x-change's control total for the provider resource;
- `system_positions` — Clearing and Legacy Unattributed attribution owned by the system principal;
- `account_positions` — the issuer's provider-specific Client Funds balance, including `not_provisioned` for absent positions;
- `account_positions.by_purpose.pay_code_reserve` — the beneficiary principal held for the in-flight provider transfer;
- `legacy_compatibility_balance_minor` — the existing Pay Code escrow and fee ledger balance;
- `liability` — outstanding, redeemed, expired, and cancelled Pay Code amounts;
- `treasury_settlement` — reservation, Position derecognition, Inventory adjustment, beneficiary principal, configured rail-fee context, and separately classified sender system-charge facts;
- Pay Code issuance and claim state; and
- a sanitized payout reconciliation with no credentials, raw provider payload, full account number, or claimant mobile.

`provider_transfer_succeeded=true` with `accounting_status=provider_sync_pending` means the payout and internal Treasury posting completed but NetBank has not yet returned the reduced balance. Rerun the exact same command with the exact same run reference. The scenario observes NetBank again and never repeats the payout.

`provider_transfer_succeeded=true` with `accounting_status=review_required` means an internal invariant failed or the provider balance is below internal attribution. This is an accounting escalation, not permission to submit another run reference. The durable run is closed specifically to prevent duplicate payment.

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
