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
- a **Legacy Unattributed Position** for authoritative opening value whose Account ownership has not yet been established.

Each Account owns one **Client Funds Position** for every active provider connection and currency. An Account can therefore have NetBank and Paynamics positions at the same time without creating a separate application user for each provider.

```text
System principal
├── netbank-primary / PHP
│   ├── Treasury Clearing
│   └── Legacy Unattributed
└── paynamics-primary / PHP
    ├── Treasury Clearing
    └── Legacy Unattributed

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

Pay Code face-value reservation and settlement remain represented by the existing Pay Code liability and execution subsystems. Non-zero instruction-fee collection still uses its existing revenue-allocation ledger path. Before enabling non-zero production fees against migrated Accounts, that charge path must be moved to an explicit Client Funds Position debit operation. This is a launch gate, not an invitation to mirror or manually synchronize two balances.

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
10. Reconcile legacy provider value before migrating any legacy Account balance.
11. Keep the simulator disabled outside local and testing environments.
12. Do not enable non-zero production instruction fees until position-backed charge allocation is implemented and accepted.
