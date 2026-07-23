# Funding Account Management

## Purpose

This document defines how x-change accepts provider-funded money without creating a manual “add funds” path. It covers NetBank, Paynamics, and the adapter boundary for future banks and EMIs.

The governing rule is:

> A provider message is evidence, not authority to credit an Account.

An Account is credited only after x-change has an open Funding Intent, validates inbound evidence, independently verifies settlement with the provider, matches the exact destination and amount, recognizes the resulting Treasury Inventory, and books the Account credit atomically.

## Canonical Grammar

x-change uses the Treasury grammar defined by `3neti/wallet`:

```text
Settlement Resource
    → Inventory
    → Allocation
    → Slice
    → Draw / Release / Repay / Reverse
```

Inbound funding stops at two explicitly separate operations:

1. recognize verified cash-backed **Inventory**;
2. book the corresponding **Account** credit.

Funding is not an Allocation or Draw. An **Account** is the user-facing accounting balance. A provider bank account or EMI wallet is a **Funding Destination**, not the Account itself. Provider-reported balance remains an external fact and is not adopted as Account truth.

## QR Ph Payer Identity

For QR Ph funding, the payer mobile is an identity claim, not settlement authority. x-change normalizes the full Philippine mobile number and resolves it only against an existing, OTP-verified `users.mobile` identity. Prefixes, first characters, Pipedream transforms, and inferred daughter-account routing are prohibited.

The order is deliberate:

```text
submit full mobile
    → resolve verified user
    → resolve Account
    → create Funding Intent
    → issue provider instructions
    → receive authenticated evidence
    → independently verify provider settlement and payer identity
    → recognize Inventory and credit Account atomically
```

An unknown or unverified mobile stops before Funding Intent creation and before provider payment. A webhook cannot create a user, verify a mobile, or choose an Account.

## Trust and Money Flow

```text
Operator creates Funding Intent
        │
        ▼
x-change resolves the selected Funding Destination
        │
        ▼
Provider adapter issues exact transfer instructions
        │
        ▼
Bank / EMI receives real money
        │
        ▼
Signed webhook stores immutable evidence
        │
        ▼
x-change independently queries the provider
        │
        ▼
Exact intent + destination + amount + currency match?
        │
   no ──┴── yes
   │         │
Suspense    Verified settlement
             │
             ▼
       Recognize Inventory
             │
             ▼
       Credit Account atomically
```

Webhook receipt never deposits to a wallet and never increments a balance directly. Duplicate delivery is absorbed by provider event identity, payload hash, Funding Intent state, and idempotent settlement references.

## Package Boundaries

| Package | Owns | Must not own |
|---|---|---|
| `3neti/emi-core` | Provider-neutral funding request, destination, instruction, observation, and verification contracts | Provider payloads, Account credit policy, Treasury mutation |
| `3neti/emi-netbank` | NetBank authentication, pristine payload normalization, VCA instruction generation, transaction verification, alias-token generation | Pipedream transforms, user selection policy, Account credit |
| `3neti/emi-paynamics` | Paynamics authentication, pristine payload normalization, wallet instruction generation, transaction verification | Treating reachability as ownership, Account credit |
| `3neti/x-change` | Funding Intent lifecycle, destination selection, evidence matching, suspense, settlement finality, Account UI, authorization, audit | Provider-specific transport internals |
| `3neti/wallet` | Treasury Inventory and Account accounting primitives | Provider payload interpretation, funding-intent policy |

The host application supplies runtime configuration and publishes package assets. It does not own this feature.

## Funding Destination Model

A Funding Destination is selected per Account owner and provider.

### Shared

Shared is the default. Provider credentials and the destination are supplied by platform configuration. The Funding Intent still carries a unique correlation reference so a shared destination cannot produce an ambiguous credit.

### Dedicated

Dedicated is optional. The Account owner supplies a provider-owned destination through the PIN-protected Cockpit Accounts workspace. Dedicated mode never silently falls back to shared mode. If its credentials or ownership proof are not ready, funding through that provider is blocked.

The active choice is represented by `FundingDestinationPreference`. Provider connection history is retained in `ProviderAccountLink`. Sensitive routing fields are encrypted at rest; read models expose masked references only.

Every Funding Intent snapshots its resolved destination. A later Account-setting change cannot redirect or reinterpret an already-issued Funding Intent.

## NetBank

### Shared destination

Shared NetBank configuration uses the platform corporate account, account name, five-digit VCA alias, and alias token.

### Dedicated destination

A dedicated NetBank destination contains:

- corporate account number;
- corporate account name;
- five-digit VCA alias;
- VCA alias token.

The token is write-only. An operator may:

- ask x-change to generate it through NetBank's authoritative alias-token API; or
- import an existing token.

Token rotation is a separate warned operation. Ordinary destination replacement cannot regenerate a token. The route requires authentication, verified email, recent PIN confirmation, throttling, validation, and audit logging.

NetBank payloads are consumed in their documented native form. No Pipedream transform, mobile-number prefix routing, or daughter-account inference is part of the architecture.

## Paynamics

### Shared destination

Shared Paynamics configuration uses the platform wallet supplied through runtime configuration.

### Dedicated destination

An operator may record a candidate Paynamics wallet ID. A successful balance or reachability lookup proves only that a wallet can be reached. It does not prove that the authenticated Account owner owns or controls that wallet.

Dedicated Paynamics funding remains blocked until a provider-authoritative ownership assertion produces `ownership_verified`. There is no silent shared fallback.

## Future Providers

A future bank or EMI integrates by implementing the `emi-core` funding contracts:

1. accept `FundingInstructionRequestData`, including the resolved destination;
2. return normalized funding instructions;
3. authenticate and normalize native webhook evidence;
4. independently verify the provider transaction;
5. return a normalized observation containing stable provider identity, destination, exact gross/fee/net amount, currency, status, occurrence time, and verification source.

Provider packages may add provider-specific destination fields internally. x-change exposes only normalized capability, readiness, masked reference, and verification state. A provider cannot authorize its own Account credit.

## Persistence and State

The principal records are:

- `FundingDestinationPreference`: active shared/dedicated selection per owner and provider;
- `ProviderAccountLink`: encrypted provider connection, masked display reference, capability and verification state;
- `FundingIntent`: expected amount, currency, provider, lifecycle state, and immutable destination snapshot;
- `ProviderFundingObservation`: normalized provider evidence;
- `FundingWebhookReceipt`: authenticated raw-delivery record and processing state;
- `FundingSettlement`: final verified posting facts;
- `FundingSuspenseCase`: mismatched or ambiguous evidence;
- `FundingReconciliationRequest`: maker-checker repair request;
- `FundingRecovery`: reversal or impairment still outstanding.

Raw secrets and routing credentials do not appear in Cockpit read models, logs, audit metadata, or validation responses.

## Security Invariants

- There is no manual credit amount control.
- Webhooks never credit an Account directly.
- A Funding Intent must precede recognition.
- Destination selection is explicit and snapshotted.
- Dedicated failures block; they do not fall back.
- Provider verification is independent from webhook intake.
- Exact amount, currency, destination, and provider identity must match.
- QR Ph payer identity must match the Account owner's full verified mobile.
- Unknown mobiles stop before Funding Intent creation or provider payment.
- Registration does not self-verify a mobile.
- Mobile verification challenges store a keyed hash, never the full mobile or OTP.
- The local `000000` verification driver is restricted to explicitly allowed environments.
- Paynamics reachability is not ownership.
- Tokens are encrypted and write-only.
- Destination mutations require a verified identity, recent PIN confirmation, and throttling.
- Connection changes and token rotation are audited without secrets.
- Reconciliation uses separate maker and checker identities.
- Reversal creates explicit recovery/impairment state; history is not rewritten.

## Cockpit Contract

`/x/cockpit/accounts` is the canonical Account-management surface.

It provides:

- masked Account and provider references;
- shared versus dedicated selection;
- NetBank enrollment and explicit token rotation;
- Paynamics ownership-verification status;
- retained connection history;
- clear warning that provider settlement, not the form submission, changes balance.

The Funding page consumes the same preference read model. A blocked dedicated destination is visible but cannot be selected for a new Funding Intent. Profile provider cards are summaries and link to Accounts; they no longer mutate provider destinations.

`/x/cockpit/funding` also exposes a non-production QR Ph lifecycle lab. Its QR carries no monetary value. The operator can simulate a ₱25 payment and inspect mobile resolution, signed evidence, authoritative re-verification, atomic posting, and replay protection. The runner performs no network call and rolls every database and balance change back.

New mobile-first registrations redirect to `/x/onboarding/mobile/verify`. The Account is not eligible for QR Ph funding until the configured OTP provider confirms the challenge. The null OTP driver and its displayed `000000` code are local/testing aids only.

## Rollback Lifecycle Scenario

The package registers `account_management_funding_destinations_demo` with the lifecycle runtime. It demonstrates the Account-management state machine without contacting NetBank or Paynamics and without retaining database or balance changes.

The runner:

1. selects shared destinations;
2. activates a synthetic dedicated NetBank destination;
3. creates an encrypted Funding Intent destination snapshot;
4. demonstrates separate write-only token rotation;
5. proves a reachable Paynamics wallet remains blocked;
6. applies synthetic ownership evidence and proves eligibility;
7. returns to shared mode while showing retained connection history.

Execution occurs inside a nested transaction. The runner always rolls back to the transaction level that existed before execution and compares the owner's funding state before and after rollback. Its public result contains only masked references and explicitly reports provider calls, balance changes, persistence, instructions, and webhooks as absent.

The scenario is available through the lifecycle CLI and the protected Cockpit Accounts walkthrough. It is blocked from the generic lifecycle HTTP API. Cockpit availability defaults to non-production environments and requires `XCHANGE_COCKPIT_ACCOUNT_SCENARIO_ENABLED=true` in production.

The QR Ph wave adds two rollback-only lifecycle scenarios:

- `qrph_funding_existing_mobile_demo` runs the signed funding pipeline for an existing verified mobile and proves an identical callback is a no-op.
- `qrph_funding_unknown_mobile_onboarding_demo` proves that an unknown mobile creates no Funding Intent, then explicitly onboards and OTP-verifies a simulated user before resuming the same funding pipeline.

Both scenarios are blocked from the generic lifecycle HTTP API. The simulator refuses production execution even if its lower-level flag is accidentally enabled.

## Configuration and Rollout

1. Configure and test shared provider credentials first.
2. Publish package migrations and assets.
3. Run migrations.
4. Configure a non-null OTP provider before enabling mobile verification in production.
5. Set independent HMAC keys for payer identity and mobile verification.
6. Confirm signature verification and provider verification endpoints in a non-production environment.
7. Keep dedicated mode optional until authoritative provider proof is available.
8. Enable provider webhook ingress only after replay and duplicate-delivery tests pass.
9. Monitor suspense, verification latency, reversal recovery, and destination failures.
10. Never migrate a legacy inferred destination into dedicated mode without explicit ownership evidence.

Relevant environment switches:

```text
XCHANGE_COCKPIT_QRPH_FUNDING_SIMULATION_ENABLED
XCHANGE_LIFECYCLE_QRPH_SIMULATION_ENABLED
XCHANGE_MOBILE_VERIFICATION_ENABLED
XCHANGE_MOBILE_VERIFICATION_TTL_MINUTES
XCHANGE_MOBILE_VERIFICATION_MAX_ATTEMPTS
XCHANGE_MOBILE_VERIFICATION_HASH_KEY
XCHANGE_FUNDING_PAYER_IDENTITY_HASH_KEY
XCHANGE_WITHDRAWAL_OTP_DRIVER
```

## Acceptance Criteria

A release is acceptable only when:

- provider adapter contract tests pass;
- x-change Funding Intent, receipt, verification, settlement, suspense, recovery, and destination tests pass;
- Accounts, Funding, and Profile frontend tests pass;
- secrets are absent from serialized read models;
- production assets build;
- package migration and asset diagnostics pass;
- responsive browser acceptance covers Accounts and Funding;
- the host worktree contains no committed implementation or documentation for this feature.
