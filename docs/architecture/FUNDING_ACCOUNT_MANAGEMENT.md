# Funding Account Management

> The provider-neutral initial-state, multi-position, reconciliation, and deployment model is documented in [Treasury Initial State and Account Portfolios](TREASURY_INITIAL_STATE_AND_ACCOUNT_PORTFOLIOS.md). This document retains the detailed Funding Account UI and provider-destination design.

## Purpose

This document defines how x-change accepts provider-funded money without creating a manual “add funds” path. It covers NetBank, Paynamics, and the adapter boundary for future banks and EMIs.

The governing rule is:

> A provider message is evidence, not authority to credit an Account.

An Account is credited only after x-change has either an exact Funding Intent or an immutable Account Funding Address binding, independently verifies provider settlement, matches the exact destination and applicable amount policy, recognizes Treasury Inventory, and books the Account credit atomically.

An issuer may also create an explicitly eligible redeemable Pay Code whose
reserved principal can be added to another user's Client Funds without a
provider payout. That is the ordinary Account Funding claim-outcome path.

For a large transfer or controlled non-QR funding source, a reviewed Funding
Request instead creates a requester-owned `PAYABLE` Voucher with an exact target
and the `x_change_account_funding` execution driver. The request, description,
and private attachment are evidence inputs only. A maker verifies the backing,
a different checker reserves already-recognized system Account Funding Reserve,
and system Treasury pays the PAYABLE once. The collection execution posts the
same value to the requester's Client Funds. The requester never claims their own
reviewed code. See
[`VOUCHER_CLAIM_OUTCOME_PROTOCOL.md`](VOUCHER_CLAIM_OUTCOME_PROTOCOL.md).

The long-lived destination design is specified in [Standing Funding Address Protocol](STANDING_FUNDING_ADDRESS_PROTOCOL.md).

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

## QR Ph Payer Identity and Self-Top-Up

“Self top-up” is a user story, not a settlement classifier. An exact flow binds the authenticated operator’s Funding Intent to their Account. A standing flow binds the exact Account Funding Address to that Account. In both cases, that pre-existing binding—not the payer mobile, webhook body, or QR scanner—determines which Account may receive the eventual credit.

The exact-amount QR carries a deterministic, intent-bound VCA. NetBank transaction history must prove that this VCA received the expected settled PHP amount. A mobile number reported by a provider is an optional identity assertion: when NetBank or another provider marks it as provider-verified, x-change compares the full normalized number with the owner’s verified mobile and sends a mismatch to suspense. When the provider does not supply payer identity, the VCA, exact amount, currency, and settlement status remain the authoritative matching facts.

Prefixes, first characters, Pipedream transforms, and inferred daughter-account routing are prohibited. A webhook cannot create a user, verify a mobile, choose an Account, or authorize credit.

## Two Funding Entry Models

### Exact Funding Intent

Use an expiring, one-time, exact-amount instruction when amount correlation and expiry are required.

### Account Funding Address

Use a stable, open-amount QR only when the exact provider destination is immutably registered with purpose `account_funding` and one Account. Provider history remains the authority. Recognition defaults to `observe_only`, with explicit `supervised` and `automatic` policies available.

The same address cannot serve `account_funding`, `funding_intent`, and `payment`. Payer mobile, amount, timing, and merchant text never choose the purpose.

## Reviewed Non-QR Funding

The reviewed path exists for sources that cannot be confirmed through the
provider-address flow: a large bank transfer, controlled cash handover,
precious metal, jewelry, a vehicle, or another approved asset.

```text
requester-owned PAYABLE is created LOCKED
        ↓
requester may attach one private PDF/JPEG/PNG
        ↓
maker records independent backing evidence and recognized value
        ↓
different checker approves and reserves system value
        ↓
PAYABLE becomes ACTIVE
        ↓
system Treasury pays the exact target through the shared collection engine
        ↓
requester Client Funds increase; PAYABLE and Settlement Envelope close
```

The typed Voucher instruction fields are the extension point:

- `voucher_type=payable`;
- `target_amount` is the requested exact value;
- `rules` require exact payment, reject overpayment, and close on full payment;
- `execution.driver=x_change_account_funding`;
- `execution.config.funding_request_reference` binds the collection to the
  reviewed request.

This is not a new Voucher type and it does not introduce an
`AccountFundingCode` model. `VoucherInstructionsData` already carries the
Voucher kind, target, rules, and execution contract required by the flow.

Attachments are stored on the Voucher Settlement Envelope using private
storage. Cockpit read models expose only sanitized document metadata and an
authenticated, owner-or-reviewer download URL. They never expose the storage
path. Downloads are `no-store`; accepted backing evidence is hash-addressed and
maker-attributed.

All payment triggers converge on `CompleteVoucherCollection`. The execution
driver selects an accounting posting implementation:

- reviewed Account Funding releases system Pay Code Reserve and credits the
  requester's Client Funds;
- provider-confirmed collection uses the provider collection posting;
- a replay returns the one existing `VoucherCollection`.

The successful first posting writes one sanitized x-journal collection entry
and publishes one owner-scoped `FundingProjectionChanged` event after commit.
No event or accounting entry is duplicated on replay.

## Provider-Verified Bank Transfer Funding

An InstaPay or bank-transfer request is a provider-backed variation of the
same requester-owned PAYABLE flow. The Account holder supplies the exact amount
and selects a search window: the configured recent window, the last hour, or
today. A sender-visible reference and screenshot are optional audit evidence
only; neither participates in authoritative matching.

```text
Account holder enters exact amount and transfer window
        ↓
receiver-side provider history observes the inbound transfer
        ↓
destination + amount + currency + status + time window match uniquely
        ↓
recent match ─────────────── older match
      ↓                          ↓
automatic collection       provider observation reserved
                                 ↓
                         system approval required
                                 ↓
                         approved collection
        ↓
x_change_provider_funding recognizes provider Inventory
        ↓
the same atomic posting allocates Client Funds to the requester
        ↓
PAYABLE closes; the request becomes Funded; Echo refreshes Cockpit
```

The receiver-side provider transaction ID is the financial idempotency key. It
is normally invisible to the sender. A sender receipt number, invoice number,
screenshot, payer name, remark, or balance delta may help later audit, but none
is sufficient to authorize credit. `x_change_funding_request_transfer_matches`
uniquely reserves both the Funding Request and provider observation before any
approval or posting. `voucher_collections` separately constrains the provider
and transaction ID at collection, so two requests cannot consume the same
inbound transfer.

`CheckFundingRequestTransfer` accepts only immutable
`ProviderFundingObservation` records with:

- the configured provider and Treasury connection;
- exact requested amount and currency;
- a creditable normalized provider status;
- `destination_verified=true`;
- an occurrence timestamp inside the selected search window.

When provider-history lookup is enabled, the check first asks the configured
`FundingProviderAdapter` to inspect the configured receiving account. The
adapter returns normalized provider evidence; x-change persists that evidence
before applying the matching rules above. Provider errors are reduced to a
sanitized failure type and never turn sender input into settlement authority.

No match leaves the request at `awaiting_provider_evidence`; multiple matches
require controlled review. Exactly one match no older than
`XCHANGE_FUNDING_BANK_TRANSFER_AUTO_CREDIT_WINDOW_MINUTES` is eligible for
automatic credit in `provider_verified_auto` mode. An older exact match is
reserved, leaves the PAYABLE locked, and moves the request to
`awaiting_approval`. Checker approval activates the same PAYABLE and completes
the `x_change_provider_funding` posting; it never draws from system Account
Funding Reserve. A replay after settlement reports `already_credited` and
performs no accounting.

NetBank VCA history already produces these observations for purpose-bound QR
addresses. With provider-history lookup enabled, the same NetBank funding
adapter queries the configured corporate receiving account for direct bank
transfers. Providers that cannot expose equivalent receiver-side history
require their own adapter or an explicitly controlled observation import. A
corporate balance delta must never be presented as transaction-history proof.

The operating mode is configurable, but provider-side evidence and exact-once
posting are not:

- `provider_verified_auto`: exact provider evidence credits automatically;
- `provider_verified_threshold`: exact provider evidence may auto-credit only
  within separately configured risk limits;
- `manual_single_control`: an authorized operator may persist facts they
  independently inspected in the provider's receiving-account dashboard;
- `manual_dual_control`: a second operator must approve the persisted
  receiver-side observation before it becomes creditable.

Production should default to `provider_verified_auto`. A manual mode relaxes
who may attest the receiver-side provider record; it never permits a sender
screenshot or arbitrary amount entry to become monetary authority.

## Trust and Money Flow

```text
Operator creates Funding Intent
        │
        ▼
x-change resolves the selected Funding Destination
        │
        ▼
NetBank registers the deterministic VCA and exact one-time limit
        │
        ▼
NetBank returns a dynamic P2M QR with the exact PHP amount
        │
        ▼
Payer scans and submits real money through QR Ph
        │
        ▼
Webhook, operator check, or schedule queues verification
        │
        ▼
x-change queries NetBank VCA transaction history
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

## Hybrid Confirmation Model

The three triggers converge on one unique, non-overlapping verification job:

```text
NetBank webhook ───────┐
Check NetBank button ──┼─→ Verify Funding Intent → settle / await / suspense
Scheduled polling ─────┘
```

- `webhook` authenticates and stores immutable intake evidence, then queues verification;
- `operator` only asks the provider to check history and accepts no amount, destination, VCA, or transaction input;
- `schedule` polls eligible open NetBank intents every minute in bounded batches.

Webhook authentication permits intake; it does not establish settlement. All three paths query the snapshotted corporate account and VCA through the same provider adapter. A settled exact match recognizes Treasury Inventory and credits the Account in the existing atomic settlement operation. No observation or a pending provider status returns the intent to `awaiting_funds`. Amount, currency, destination, identity, or ambiguity failures enter suspense. A provider outage records only a sanitized failure type and returns the intent to `awaiting_funds`.

The queued job is unique per Funding Intent, shares a non-overlapping lock across triggers, and uses a provider-scoped rate limiter. Replayed webhooks and simultaneous manual/scheduled checks therefore cannot produce a second settlement.

## Package Boundaries

| Package | Owns | Must not own |
|---|---|---|
| `3neti/emi-core` | Provider-neutral funding request, destination, instruction, observation, and verification contracts | Provider payloads, Account credit policy, Treasury mutation |
| `3neti/emi-netbank` | NetBank authentication, pristine payload normalization, VCA instruction generation, transaction verification, ephemeral registration-token generation | Pipedream transforms, user selection policy, Account credit |
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

Shared NetBank configuration uses the platform corporate account, account name, and five-digit VCA alias.

### Dedicated destination

A dedicated NetBank destination contains:

- corporate account number;
- corporate account name;
- five-digit VCA alias.

Pre-transaction validation tokens are not Account credentials. NetBank confirmed
on 2026-07-24 that generating a new token does not overwrite or invalidate an
existing token and that each generated token is intended for a new VCA
registration. The adapter therefore generates a fresh token immediately before
each registration, uses it only in memory, and never stores it in platform
configuration, `ProviderAccountLink`, or a Funding Intent snapshot. Cockpit does
not expose token generation, import, or rotation controls.

NetBank payloads are consumed in their documented native form. No Pipedream transform, mobile-number prefix routing, or daughter-account inference is part of the architecture.

### Exact-amount QR issuance

For each NetBank Funding Intent, the adapter:

1. derives the same numeric VCA reference from the Funding Intent on every retry;
2. generates a fresh pre-transaction validation token for the snapshotted corporate account and five-digit alias;
3. registers that reference with the newly generated token;
4. applies a one-time minimum and maximum equal to the exact intent amount;
5. asks NetBank to generate a dynamic P2M QR for the VCA, PHP amount, and intent reference;
6. accepts only a valid base64-encoded PNG response.

The QR contract is provider-neutral and records its MIME type, base64 payload, dynamic/static mode, transaction type, embedded-amount flag, and whether the provider generated it. Existing provider adapters remain compatible because the QR artifact is optional.

If QR generation fails, the Funding Intent remains `pending_instructions`. A retry reuses the deterministic VCA; it does not create another Account credit opportunity. QR image data, credentials, account numbers, and raw provider response bodies are excluded from logs.

NetBank stays unavailable until OAuth credentials, corporate account details, the five-digit VCA alias, QR endpoint, merchant name, merchant city, and purpose are configured.

### NetBank settlement authority

The adapter retrieves the intent VCA’s transaction history under the snapshotted corporate account. It accepts exactly one incoming credit candidate, normalizes amounts to minor units, and returns a hash-addressed observation. Multiple exact candidates are ambiguous. A single non-exact candidate is evidence of a mismatch. x-change—not the adapter—decides whether that observation settles, awaits, or enters suspense.

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
- `StandingFundingAddress`: encrypted provider destination, immutable purpose/Account binding, mode, status, and limits;
- `AccountFundingReceipt`: one classified provider transaction and its recognition state.

Raw secrets and routing credentials do not appear in Cockpit read models, logs, audit metadata, or validation responses.

## Security Invariants

- There is no manual credit amount control.
- Webhooks never credit an Account directly.
- Manual and scheduled checks never accept operator-supplied transaction facts.
- An exact Funding Intent or immutable Account Funding Address must precede recognition.
- Destination selection is explicit and snapshotted.
- Dedicated failures block; they do not fall back.
- Provider verification is independent from webhook intake.
- Exact amount, currency, destination, and provider identity must match.
- The authenticated Funding Intent owner determines the Account to credit.
- Payer mobile is never a routing key; provider-verified identity is an additional check when supplied.
- Registration does not self-verify a mobile.
- Mobile verification challenges store a keyed hash, never the full mobile or OTP.
- The local `000000` verification driver is restricted to explicitly allowed environments.
- Paynamics reachability is not ownership.
- Tokens are encrypted and write-only.
- Destination mutations require a verified identity, recent PIN confirmation, and throttling.
- Connection changes are audited without secrets; ephemeral registration tokens are never audit inputs.
- Reconciliation uses separate maker and checker identities.
- Reversal creates explicit recovery/impairment state; history is not rewritten.

## Cockpit Contract

`/x/cockpit/accounts` is the canonical Account-management surface.

It provides:

- masked Account and provider references;
- shared versus dedicated selection;
- NetBank account/alias routing without stored registration tokens;
- Paynamics ownership-verification status;
- retained connection history;
- clear warning that provider settlement, not the form submission, changes balance.

The Funding page consumes the same preference read model. A blocked dedicated destination is visible but cannot be selected for a new Funding Intent. Profile provider cards are summaries and link to Accounts; they no longer mutate provider destinations.

An eligible NetBank intent exposes:

- a one-time instruction card with the provider-generated QR, exact amount, expiry, and destination guidance;
- an owner-only **Reopen QR** control backed by a private `no-store` endpoint;
- a throttled **Check NetBank** control that only queues the shared verification job;
- status, last-check time, and a compact result notice.

The general Funding read model never contains a VCA, QR payload, provider transaction ID, or raw evidence. The secure instruction endpoint reopens only an unexpired owner-bound instruction. While open intents exist, the page refreshes only the Funding props using non-overlapping Inertia polling and preserves the sensitive instruction already present in browser state.

`/x/cockpit/funding` also exposes a non-production QR Ph lifecycle lab. Its QR carries no monetary value. The operator can simulate a ₱25 payment and inspect mobile resolution, signed evidence, authoritative re-verification, atomic posting, and replay protection. The runner performs no network call and rolls every database and balance change back.

New mobile-first registrations redirect to `/x/onboarding/mobile/verify`. The Account is not eligible for QR Ph funding until the configured OTP provider confirms the challenge. The null OTP driver and its displayed `000000` code are local/testing aids only.

## Rollback Lifecycle Scenario

The package registers `account_management_funding_destinations_demo` with the lifecycle runtime. It demonstrates the Account-management state machine without contacting NetBank or Paynamics and without retaining database or balance changes.

The runner:

1. selects shared destinations;
2. activates a synthetic dedicated NetBank destination;
3. creates an encrypted Funding Intent destination snapshot;
4. demonstrates that registration tokens are absent from Account state and Funding Intent snapshots;
5. proves a reachable Paynamics wallet remains blocked;
6. applies synthetic ownership evidence and proves eligibility;
7. returns to shared mode while showing retained connection history.

Execution occurs inside a nested transaction. The runner always rolls back to the transaction level that existed before execution and compares the owner's funding state before and after rollback. Its public result contains only masked references and explicitly reports provider calls, balance changes, persistence, instructions, and webhooks as absent.

The scenario is available through the lifecycle CLI and the protected Cockpit Accounts walkthrough. It is blocked from the generic lifecycle HTTP API. Cockpit availability defaults to non-production environments and requires `XCHANGE_COCKPIT_ACCOUNT_SCENARIO_ENABLED=true` in production.

The QR Ph wave adds two rollback-only lifecycle scenarios:

- `qrph_funding_existing_mobile_demo` runs the signed funding pipeline for an existing verified mobile and proves an identical callback is a no-op.
- `qrph_funding_unknown_mobile_onboarding_demo` proves that an unknown mobile creates no Funding Intent, then explicitly onboards and OTP-verifies a simulated user before resuming the same funding pipeline.

Both scenarios are blocked from the generic lifecycle HTTP API. The simulator refuses production execution even if its lower-level flag is accidentally enabled.

## Scheduled Verification and Expiry

The package registers:

```text
xchange:funding:verify-open --provider=netbank --limit=100
xchange:funding:sync-standing --provider=netbank --limit=100
```

It also registers a non-overlapping every-minute schedule when scheduled verification is enabled. The host must run Laravel’s scheduler and queue worker, but it contains no funding command, schedule, or business rule.

Open NetBank intents are checked until their instruction expiry plus the configured settlement grace period, which defaults to five minutes. Once that boundary passes, x-change performs a final authoritative provider-history query. It expires the intent only after NetBank conclusively reports no matching transfer beyond the grace boundary. Provider outages continue to await verification; they are never interpreted as proof that no payment occurred. Verified intents remain eligible so a failed Account-credit attempt can recover through the idempotent settlement action.

Funding Intent events retain the verification trigger, attempt transition, outcome, expiry, and sanitized provider-failure type. Cockpit projects only safe statuses and timestamps.

## Configuration and Rollout

1. Configure and test shared provider credentials first.
2. Publish package migrations and assets.
3. Run migrations.
4. Configure a non-null OTP provider before enabling mobile verification in production.
5. Set independent HMAC keys for payer identity and mobile verification.
6. Confirm signature verification and provider verification endpoints in a non-production environment.
7. Keep dedicated mode optional until authoritative provider proof is available.
8. Enable provider webhook ingress only after replay and duplicate-delivery tests pass.
9. Run a queue worker and Laravel scheduler; confirm the named NetBank task appears once.
10. Confirm owner-only QR access returns private `no-store` responses.
11. Monitor suspense, verification latency, expiry, reversal recovery, and destination failures.
12. Never migrate a legacy inferred destination into dedicated mode without explicit ownership evidence.

Relevant environment switches:

```text
XCHANGE_FUNDING_NETBANK_ENABLED
XCHANGE_FUNDING_INTENT_TTL_SECONDS
XCHANGE_FUNDING_SCHEDULED_VERIFICATION_ENABLED
XCHANGE_FUNDING_SCHEDULED_VERIFICATION_BATCH_SIZE
XCHANGE_FUNDING_SETTLEMENT_GRACE_SECONDS
XCHANGE_FUNDING_VERIFICATION_PROVIDER_RATE_LIMIT_PER_MINUTE
XCHANGE_FUNDING_UI_REFRESH_INTERVAL_MILLISECONDS
XCHANGE_FUNDING_BROADCAST_ENABLED
XCHANGE_FUNDING_BROADCAST_REFERENCE_HASH_KEY
XCHANGE_STANDING_FUNDING_ADDRESSES_ENABLED
XCHANGE_STANDING_FUNDING_RECOGNITION_MODE
XCHANGE_STANDING_FUNDING_ENFORCE_RECOGNITION_MODE
XCHANGE_STANDING_FUNDING_CREDITABLE_STATUSES
XCHANGE_STANDING_FUNDING_QR_ARTIFACT_VERSION
XCHANGE_STANDING_FUNDING_QR_LOCK_SECONDS
XCHANGE_STANDING_FUNDING_QR_LOCK_WAIT_SECONDS
XCHANGE_STANDING_FUNDING_SCHEDULED_SYNC_ENABLED
XCHANGE_STANDING_FUNDING_SCHEDULED_BATCH_SIZE
XCHANGE_STANDING_FUNDING_WEBHOOK_BATCH_SIZE
XCHANGE_STANDING_FUNDING_MINIMUM_AMOUNT_MINOR
XCHANGE_STANDING_FUNDING_MAXIMUM_AMOUNT_MINOR
XCHANGE_STANDING_FUNDING_DAILY_LIMIT_MINOR
XCHANGE_FUNDING_BANK_TRANSFER_ENABLED
XCHANGE_FUNDING_BANK_TRANSFER_PROVIDER
XCHANGE_FUNDING_BANK_TRANSFER_CONNECTION
XCHANGE_FUNDING_BANK_TRANSFER_VERIFICATION_MODE
XCHANGE_FUNDING_BANK_TRANSFER_PROVIDER_HISTORY_ENABLED
XCHANGE_FUNDING_BANK_TRANSFER_AUTO_CREDIT_WINDOW_MINUTES
XCHANGE_FUNDING_BANK_TRANSFER_CLOCK_SKEW_SECONDS
XCHANGE_COCKPIT_QRPH_FUNDING_SIMULATION_ENABLED
XCHANGE_LIFECYCLE_QRPH_SIMULATION_ENABLED
XCHANGE_MOBILE_VERIFICATION_ENABLED
XCHANGE_MOBILE_VERIFICATION_TTL_MINUTES
XCHANGE_MOBILE_VERIFICATION_MAX_ATTEMPTS
XCHANGE_MOBILE_VERIFICATION_HASH_KEY
XCHANGE_FUNDING_PAYER_IDENTITY_HASH_KEY
XCHANGE_WITHDRAWAL_OTP_DRIVER

NETBANK_FUNDING_API_URL
NETBANK_FUNDING_TOKEN_URL
NETBANK_FUNDING_CLIENT_ID
NETBANK_FUNDING_CLIENT_SECRET
NETBANK_FUNDING_CORPORATE_ACCOUNT_NUMBER
NETBANK_FUNDING_CORPORATE_ACCOUNT_NAME
NETBANK_FUNDING_VCA_ALIAS
NETBANK_FUNDING_REFERENCE_KEY
NETBANK_FUNDING_STANDING_ADDRESS_SCHEME
NETBANK_FUNDING_VCA_REFERENCE_LENGTH
NETBANK_FUNDING_STANDING_HMAC_KEY_ID
NETBANK_FUNDING_STANDING_HMAC_KEY
NETBANK_FUNDING_QR_ENDPOINT
NETBANK_FUNDING_QR_MERCHANT_NAME
NETBANK_FUNDING_QR_MERCHANT_CITY
NETBANK_FUNDING_QR_PURPOSE
NETBANK_FUNDING_WEBHOOK_ALLOWED_IPS

MERCHANT_QR_DEFAULT_CITY
MERCHANT_QR_DEFAULT_CATEGORY_CODE
MERCHANT_QR_DEFAULT_NAME_TEMPLATE
MERCHANT_QR_FALLBACK_NAME
MERCHANT_QR_UPPERCASE
```

One-time Funding Intents generate their pre-transaction validation token through
NetBank immediately before VCA registration. No
`NETBANK_FUNDING_VCA_ALIAS_TOKEN` is configured. A shared reusable Account
Funding Address does not register a VCA and therefore does not generate or
consume this token. Its default scheme is `netbank-mobile-v1` outside production
and `netbank-account-hmac-v2` in production; production rejects the mobile
scheme. The HMAC key is dedicated, must be at least 32 bytes, and must never
fall back to `APP_KEY`.

The reusable Account Funding QR is persisted as an encrypted fixture and reopened through an owner-only `no-store` endpoint. A changed merchant profile invalidates that fixture on the next open but never changes the persisted funding address. `XCHANGE_FUNDING_BROADCAST_ENABLED` defaults to `false`; enable it only when Reverb or another private-channel broadcaster is continuously managed. Realtime delivery is not settlement authority: the Account credit commits first, a broadcast failure is sanitized and audited, and direct post-action reload and polling remain supported fallbacks. **Check NetBank** honors the server `Retry-After` response and does not issue overlapping retries during its cooldown.

Merchant name, city, category, and display template are presentation inputs. They must never participate in Account routing, observation classification, or settlement authorization.

Before production, run adapter contract tests with HTTP fakes, verify the queue and scheduler in a non-production environment, and exercise browser acceptance without sending money. Live UAT is a separate explicit gate: a human scans a configured small exact-amount QR and authorizes the real payment. The acceptance result must contain exactly one Inventory recognition and one Account credit whether confirmation arrives through webhook or **Check NetBank**. Automated tests and Codex must never initiate the real-money payment.

## Acceptance Criteria

A release is acceptable only when:

- provider adapter contract tests pass;
- x-change Funding Intent, receipt, verification, settlement, suspense, recovery, and destination tests pass;
- Accounts, Funding, and Profile frontend tests pass;
- secrets are absent from serialized read models;
- production assets build;
- package migration and asset diagnostics pass;
- responsive browser acceptance covers Accounts and Funding;
- live money movement is either explicitly gated and human-authorized or recorded as pending;
- authored implementation and documentation remain inside the package repositories; the host may contain only published package assets and generated route bindings.

## Hybrid NetBank Wave Acceptance Record

Acceptance completed on 2026-07-23:

- `emi-core`: QR contract, serialization, legacy-adapter compatibility, observations, and webhook evidence passed;
- `emi-netbank`: deterministic VCA, exact one-time limit, dynamic P2M QR, PNG validation, readiness, retry, transaction-history, ambiguity, and failure-path tests passed;
- `x-change`: Funding Intent lifecycle, instruction persistence, owner authorization, secure QR access, webhook/operator/schedule convergence, unique job controls, settlement, suspense, recovery, expiry, Accounts, Funding read models, and lifecycle scenarios passed;
- Cockpit Funding and Accounts component tests passed;
- published Cockpit assets matched package source;
- production assets built successfully;
- desktop acceptance at 1440×1000 and mobile acceptance at 390×844 showed no document-level horizontal overflow;
- a local Funding Intent rendered exact one-time instructions; an owner-bound temporary NetBank fixture reopened a dynamic P2M QR through the secure endpoint;
- the mobile QR remained 160×160, the instruction card fit the viewport, and the wide activity table remained reachable through its own horizontal scroller;
- invalid exact-amount input rendered its inline error without overflowing;
- the seven-step rollback-only QR Ph scenario completed, proved a single simulated credit plus replay no-op, and retained no lifecycle or balance changes;
- temporary browser-acceptance records were removed afterward.

NetBank was intentionally disabled in the sandbox, so its provider option and **Check NetBank** control remained disabled. Authorization, queueing, pending/suspense outcomes, `no-store` headers, and QR persistence are covered by backend and component tests. Live QR generation, human scan/payment, webhook arrival, and real VCA history confirmation remain behind the explicit live-provider UAT gate; no automated real-money payment was performed.
