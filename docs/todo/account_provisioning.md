# Strategy and Implementation Plan - Account Provisioning Across x-change, onboarding, and EMI Packages

## Status

Revised after inspecting the actual provider packages:

- `/Users/rli/PhpstormProjects/packages/emi-core`
- `/Users/rli/PhpstormProjects/packages/emi-netbank`
- `/Users/rli/PhpstormProjects/packages/emi-paynamics`

The important correction is that `emi-core` already owns provider-side account primitives:

- `LBHurtado\EmiCore\Models\ProviderAccount`
- `LBHurtado\EmiCore\Models\Wallet`
- `LBHurtado\EmiCore\Models\WalletProfile`
- `LBHurtado\EmiCore\Models\BankAccount`
- `LBHurtado\EmiCore\Models\Transaction`
- `LBHurtado\EmiCore\Models\Transfer`
- `LBHurtado\EmiCore\Models\CashIn`
- `LBHurtado\EmiCore\Models\CashOut`
- `LBHurtado\EmiCore\Models\OtpChallenge`

Therefore, `3neti/x-change` should not create a duplicate low-level `provider_accounts` table that tries to mirror EMI internals. x-change should own the product policy, onboarding orchestration, UI projection, and the mapping between an x-change owner and the relevant EMI provider records.

## Goal

Make x-change a turnkey mobile-first Pay Code OS that can onboard an issuer/redeemer, determine which provider capability is needed, delegate provider-specific work to EMI packages, and resume the Pay Code lifecycle once the account is ready.

The target model is:

```text
x-change decides why account provisioning is needed.
onboarding records the user's readiness journey.
emi-core provides normalized EMI contracts and persistence primitives.
emi-netbank and emi-paynamics execute provider-specific API calls.
x-change stores owner-to-provider-account links and renders the product UX.
```

## Provider Topologies

The current topologies remain correct:

```text
netbank    -> ledger_pooled
paynamics  -> provider_customer_wallet
manual     -> manual
```

### NetBank topology

NetBank is currently a pooled-ledger provider integration.

Observed source:

- `LBHurtado\PaymentGateway\Adapters\NetbankPayoutProvider`
- `LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface`
- `LBHurtado\PaymentGateway\Contracts\WalletProxy`
- `config/omnipay.php`
- `config/disbursement.php`

Runtime meaning:

```text
x-change user balance/source of truth -> local ledger wallet
provider account source                -> configured NetBank source account
provider credentials                   -> NETBANK_* env/config
per-user provider wallet               -> not required for first slice
redeemer bank account                  -> collected/validated as payout destination
```

NetBank provisioning should start as bank-account readiness and ledger-wallet readiness, not provider customer-wallet creation.

### Paynamics topology

Paynamics Constellation is a provider-customer-wallet topology.

Observed source:

- `LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\AddCustomerWallet`
- `LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletDetails`
- `LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletBalance`
- `LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GenerateKycKybLink`
- `LBHurtado\EmiPaynamicsConstellation\Actions\BankAccounts\AddBankAccount`
- `LBHurtado\EmiPaynamicsConstellation\Actions\Transfers\PreTransfer`
- `LBHurtado\EmiPaynamicsConstellation\Actions\Transfers\SettleTransfer`
- `LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationPayoutProvider`
- `config/constellation.php`

Runtime meaning:

```text
x-change user balance/source of truth -> provider customer wallet
provider account source                -> Paynamics merchant/settlement wallet
provider credentials                   -> CONSTELLATION_* env/config
per-user provider wallet               -> required for real turnkey issuer/customer mode
redeemer bank account                  -> Paynamics cash-out destination, with OTP approval where required
```

Paynamics provisioning should create or resolve a customer wallet, optionally create/bind a bank account, and store the resulting EMI wallet/bank-account references.

## Credential and Settings Model

There are two different credential classes, and they must not be mixed.

### Platform provider API credentials

These belong to the host application/operator/merchant. They let the application talk to Paynamics, NetBank, or another provider.

Examples:

```text
Paynamics:
CONSTELLATION_USERNAME
CONSTELLATION_PASSWORD
CONSTELLATION_MERCHANT_KEY
CONSTELLATION_BASE_URL
CONSTELLATION_SETTLEMENT_WALLET_ID
CONSTELLATION_REVENUE_WALLET_ID

NetBank:
NETBANK_CLIENT_ID
NETBANK_CLIENT_SECRET
NETBANK_SOURCE_ACCOUNT_NUMBER
NETBANK_SENDER_CUSTOMER_ID
NETBANK_* endpoints
```

These should come from provider package config:

```text
Paynamics -> config('constellation.*')
NetBank   -> config('omnipay.*') and config('disbursement.*')
```

Do not copy these into onboarding sessions, voucher metadata, provider account links, frontend props, or lifecycle payloads.

### User-side account authorization

These belong to the onboarding subject.

For Paynamics, onboarding may create or resolve a real provider customer wallet for the user. x-change should store provider wallet/account identifiers and readiness state, not raw user secrets.

For NetBank, the current topology does not give each user NetBank API credentials. The user normally provides destination bank account details and consent, while x-change uses the operator's NetBank credentials and local ledger wallet.

The practical rule is:

```text
Host app has provider API credentials.
User onboarding establishes the user's provider wallet, bank account, consent, or readiness state.
```

### Runtime settings

Some values are operational settings rather than secrets. These may need to be changed by an operator without code changes.

Examples:

```text
active provider
provider topology override
provider enabled/disabled flags
default settlement rail
live-provider lifecycle scenario opt-in
manual funding instructions
minimum/maximum test amount
masked source account display value
onboarding enforcement mode
```

Use a config-first resolver boundary:

```text
ProviderRuntimeSettingsResolverContract
    -> ConfigProviderRuntimeSettingsResolver
    -> SpatieProviderRuntimeSettingsResolver, optional
```

`spatie/laravel-settings` is a good optional implementation for strongly typed, operator-editable runtime settings. It should not become a hard dependency in the first provisioning slice. If used, encrypt sensitive properties and still prefer `.env`, encrypted config, or a secret manager for raw API secrets.

## Package Ownership

### `3neti/onboarding`

Owns generic readiness journey state:

- onboarding session/reference
- onboarding purpose
- required subject data
- auth/readiness guard
- completion state

Should not own:

- provider credentials
- Paynamics request signatures
- NetBank request signing
- x-change voucher policy
- provider account persistence internals
- provider API execution

### `3neti/emi-core`

Owns normalized EMI primitives:

- provider account records
- provider wallet records
- wallet profiles
- bank account records
- transactions, transfers, cash-ins, cash-outs, OTP challenges
- provider contracts:
  - `WalletProvider`
  - `PayoutProvider`
  - `CashInProvider`
  - `CashOutProvider`
  - `TransferProvider`
  - `SystemReadiness`
  - `BankRegistryContract`

This package is the correct place for provider-facing account/wallet/bank-account persistence that is not specific to x-change.

### `3neti/emi-paynamics`

Owns Paynamics Constellation API details:

- customer wallet creation
- merchant wallet creation
- wallet details/balance
- KYC/KYB link generation
- bank account binding
- cash-in/cash-out
- cash-out OTP resolver behavior
- wallet-to-wallet transfer
- Constellation readiness checks

x-change should consume these through provider-specific adapter services, not call HTTP endpoints directly.

### `3neti/emi-netbank`

Owns NetBank API details:

- Omnipay gateway config
- payout/disbursement adapter
- account balance checks
- QR/collection helpers
- configured source account and credentials

For the first account provisioning slice, NetBank does not need per-user provider wallet creation. It needs local wallet readiness plus destination bank-account collection/validation.

### `3neti/x-change`

Owns product orchestration:

- provider topology selection
- onboarding purpose selection
- Pay Code issuance/claim policy
- readiness gate before issuance or claim
- mapping an x-change owner to EMI records
- user-facing provisioning flow descriptors
- lifecycle scenario verification
- install/doctor diagnostics

## Data Model Strategy

### Do not add a duplicate low-level `provider_accounts` table in x-change

The previous plan proposed:

```text
x-change.provider_accounts
```

That conflicts with `emi-core.provider_accounts` and should be avoided.

### Add x-change owner link records instead

Add an x-change-owned table whose job is to link a Laravel owner to an EMI record and x-change readiness policy.

Suggested table:

```text
xchange_provider_account_links
```

Suggested schema:

```php
Schema::create('xchange_provider_account_links', function (Blueprint $table) {
    $table->id();

    $table->morphs('owner');

    $table->string('provider');              // paynamics, netbank
    $table->string('topology');              // provider_customer_wallet, ledger_pooled
    $table->string('purpose')->nullable();   // IssuePayCode, RedeemPayCode, BankOnboardingRequired
    $table->string('mode')->nullable();      // wallet_create, bank_account_link, ledger_wallet, hybrid

    $table->foreignId('emi_provider_account_id')->nullable();
    $table->foreignId('emi_wallet_id')->nullable();
    $table->foreignId('emi_bank_account_id')->nullable();

    $table->string('provider_account_id')->nullable();
    $table->string('provider_wallet_id')->nullable();
    $table->string('provider_bank_account_id')->nullable();
    $table->string('external_uid')->nullable();

    $table->string('status')->default('pending');
    $table->string('verification_status')->nullable();
    $table->string('identity_level')->nullable();
    $table->json('capabilities')->nullable();
    $table->json('metadata')->nullable();

    $table->timestamp('ready_at')->nullable();
    $table->timestamp('last_synced_at')->nullable();
    $table->timestamps();

    $table->index(['provider', 'topology']);
    $table->index(['provider', 'provider_account_id']);
    $table->index(['provider', 'provider_wallet_id']);
    $table->index(['provider', 'external_uid']);
});
```

Notes:

- `emi_provider_account_id`, `emi_wallet_id`, and `emi_bank_account_id` point to EMI package records when they exist.
- `provider_*` string columns make the link useful even before full EMI model hydration.
- Store raw provider responses in `metadata` only after redacting credentials and secrets.
- Do not store platform credentials here.
- Do not store raw user bank passwords, raw OTPs, or raw provider portal credentials here.

Suggested model:

```text
LBHurtado\XChange\Models\ProviderAccountLink
```

This is an x-change ownership/readiness link, not the canonical provider account model.

## Contracts to Add in x-change

### Provider account link repository

```php
namespace LBHurtado\XChange\Contracts;

interface ProviderAccountLinkRepositoryContract
{
    public function storeFromProvisioningResult(mixed $owner, mixed $result): mixed;

    public function findReadyForOwner(mixed $owner, string $provider, ?string $mode = null): mixed;

    public function findLatestForOwner(mixed $owner, string $provider, ?string $mode = null): mixed;
}
```

The concrete repository should write `ProviderAccountLink` rows and optionally resolve matching `emi-core` records.

### Provider provisioning gateway

```php
namespace LBHurtado\XChange\Contracts;

interface ProviderProvisioningGatewayContract
{
    public function supports(string $provider, string $mode): bool;

    public function provision(mixed $owner, array $payload): mixed;

    public function refresh(mixed $link): mixed;
}
```

This lets x-change call provider provisioning without knowing whether Paynamics creates a customer wallet or NetBank validates a ledger/destination bank account.

### Provider provisioning manager

```php
namespace LBHurtado\XChange\Contracts;

interface ProviderProvisioningManagerContract
{
    public function startOrResume(mixed $owner, array $payload): mixed;
}
```

The manager resolves topology, provider, mode, onboarding reference, and the correct provider gateway.

### Provider runtime settings resolver

```php
namespace LBHurtado\XChange\Contracts;

interface ProviderRuntimeSettingsResolverContract
{
    public function provider(?string $override = null): string;

    public function topology(?string $provider = null): string;

    public function isEnabled(string $provider): bool;

    public function allowsLiveProviderScenarios(): bool;

    public function setting(string $key, mixed $default = null): mixed;
}
```

The default implementation should read `config('x-change.*')` and provider package config. A Spatie-backed implementation may be added later for host apps that want an admin-editable settings surface.

## Provider-Specific Gateways

### Paynamics gateway

Suggested class:

```text
LBHurtado\XChange\Services\Provisioning\PaynamicsProviderProvisioningGateway
```

Responsibilities:

1. Build a Paynamics customer-wallet payload from mobile-first x-change/onboarding subject data.
2. Call `AddCustomerWallet`.
3. Persist or update `emi-core.wallets`.
4. Optionally call `GenerateKycKybLink` when verification is required.
5. Optionally call `AddBankAccount` when the flow requires a registered bank account.
6. Store/update `ProviderAccountLink`.

First slice modes:

```text
wallet_create
wallet_resolve
bank_account_link
hybrid
```

Minimum Paynamics output shape for x-change:

```php
[
    'provider' => 'paynamics',
    'topology' => 'provider_customer_wallet',
    'mode' => 'wallet_create',
    'status' => 'pending|ready|failed',
    'provider_wallet_id' => 'CNSTWLLT...',
    'provider_account_id' => '...',
    'external_uid' => '...',
    'verification_status' => 'PENDING|APPROVED|...',
    'capture_url' => 'https://...',
    'raw' => [...],
]
```

Credential source:

```text
config/constellation.php
CONSTELLATION_BASE_URL
CONSTELLATION_USERNAME
CONSTELLATION_PASSWORD
CONSTELLATION_MERCHANT_KEY
CONSTELLATION_SETTLEMENT_WALLET_ID
CONSTELLATION_REVENUE_WALLET_ID
CONSTELLATION_DEFAULT_ISSUER_WALLET_ID
```

Do not duplicate these into `x-change.provider_credentials`. If host operators need to edit non-secret runtime values in the UI, resolve those through `ProviderRuntimeSettingsResolverContract`.

### NetBank gateway

Suggested class:

```text
LBHurtado\XChange\Services\Provisioning\NetbankProviderProvisioningGateway
```

Responsibilities:

1. Ensure the local ledger wallet exists for the owner through `WalletProvisioningContract`.
2. Record bank-account readiness when destination bank details are collected.
3. Optionally check rails and configured source-account readiness through NetBank gateway/readiness calls.
4. Store/update `ProviderAccountLink`.

First slice modes:

```text
ledger_wallet
bank_account_link
```

Minimum NetBank output shape for x-change:

```php
[
    'provider' => 'netbank',
    'topology' => 'ledger_pooled',
    'mode' => 'bank_account_link',
    'status' => 'ready',
    'provider_account_id' => null,
    'provider_wallet_id' => null,
    'bank_code' => '...',
    'account_number_masked' => '...',
    'raw' => [],
]
```

Credential source:

```text
config/omnipay.php
config/disbursement.php
NETBANK_CLIENT_ID
NETBANK_CLIENT_SECRET
NETBANK_SOURCE_ACCOUNT_NUMBER
NETBANK_SENDER_CUSTOMER_ID
NETBANK_* endpoints
```

Do not duplicate these into `x-change.provider_credentials`. If host operators need to edit non-secret runtime values in the UI, resolve those through `ProviderRuntimeSettingsResolverContract`.

## Provisioning Modes

Keep provisioning mode vocabulary in x-change first. Move to onboarding only if multiple packages need it independently.

Suggested enum:

```text
LBHurtado\XChange\Enums\ProviderProvisioningMode
```

Cases:

```php
enum ProviderProvisioningMode: string
{
    case LedgerWallet = 'ledger_wallet';
    case WalletCreate = 'wallet_create';
    case WalletResolve = 'wallet_resolve';
    case WalletUpgrade = 'wallet_upgrade';
    case BankAccountLink = 'bank_account_link';
    case Hybrid = 'hybrid';
}
```

Default mapping:

```text
manual    -> ledger_wallet
netbank   -> ledger_wallet or bank_account_link
paynamics -> wallet_create or wallet_resolve
```

## UI/UX Projection

Keep UI projection in x-change, not onboarding and not EMI packages.

Suggested DTO:

```text
LBHurtado\XChange\Data\ProvisioningFlowDescriptorData
```

Suggested fields:

```php
[
    'provider' => 'paynamics',
    'topology' => 'provider_customer_wallet',
    'mode' => 'wallet_create',
    'title' => 'Create your Paynamics wallet',
    'description' => 'Complete wallet setup so Pay Codes can be issued and paid out.',
    'steps' => ['profile', 'wallet', 'kyc', 'bank_account', 'consent'],
    'fields' => ['mobile', 'name', 'email', 'address', 'source_of_funds'],
    'actions' => ['continue', 'open_capture_link'],
    'metadata' => [],
]
```

Recommended first descriptors:

### Paynamics issuer wallet

```text
title: Create your Paynamics wallet
steps: profile -> wallet -> KYC link -> ready
target user: issuer/customer wallet owner
```

### Paynamics bank account link

```text
title: Add your payout bank account
steps: bank account -> consent -> provider bind -> ready
target user: customer wallet owner
```

### NetBank bank account readiness

```text
title: Add payout destination
steps: bank account -> consent -> ready
target user: redeemer or issuer depending on flow
```

## Claim and Issuance Integration

### Issuer flow

```text
Issuer registers with mobile + PIN.
x-change resolves provider topology.
If provider is NetBank:
    create local ledger wallet if missing.
    issuer can issue Pay Codes once local wallet and balance policy pass.
If provider is Paynamics:
    require or resolve provider customer wallet.
    if wallet is missing, start onboarding/provisioning.
    issuer can issue Pay Codes once wallet readiness policy passes.
```

### Redeemer flow

```text
Redeemer submits claim.
x-change inspects claim requirements and provider topology.
If bank onboarding is required:
    start onboarding purpose BankOnboardingRequired.
    collect/validate destination account.
    store provider account link or bank-account readiness.
After onboarding is ready:
    resume claim.
If provider requires payout OTP:
    issuer/admin approves OTP in approval surface.
Redeemer waits and then sees success/rider redirect.
```

## Lifecycle Scenario Runner Integration

Account provisioning is not complete until it has executable lifecycle coverage. Static checks are useful, but they do not prove that x-change can move through onboarding, provider readiness, claim guards, and resume behavior.

The lifecycle runner should become the acceptance layer for this plan.

Existing group:

```bash
php artisan xchange:lifecycle:run-group turnkey-onboarding --no-claim --timeout=1 --poll=1 --max-polls=1
```

### Scenario layers

#### Layer 1 - Static turnkey readiness

Existing or current scenarios:

```text
turnkey_mobile_boot
turnkey_bank_onboarding_required
turnkey_basic_cash_mobile
```

These prove the installed host can resolve mobile-first auth, issuer mobile channel, provider topology, and onboarding gateway boundaries.

#### Layer 2 - Provider account link readiness

Add scenarios:

```text
turnkey_provider_link_ready
turnkey_provider_link_pending_blocks
```

These prove:

```text
xchange_provider_account_links can be created
pending -> ready transitions are visible
ready links can be resolved for an owner/provider/mode
missing or pending links block gated flows
```

#### Layer 3 - NetBank ledger topology

Add scenarios:

```text
turnkey_provider_link_netbank
turnkey_provider_bank_account_required
turnkey_netbank_ledger_wallet_ready
turnkey_netbank_bank_account_ready
```

These should be credential-safe by default and should not disburse funds. They prove local ledger wallet readiness and bank-account readiness for the pooled-ledger topology.

#### Layer 4 - Paynamics customer-wallet topology

Add scenarios:

```text
turnkey_paynamics_wallet_fake_provisioned
turnkey_paynamics_bank_account_fake_linked
turnkey_paynamics_wallet_link_ready
```

These should use fake Paynamics responses by default. They prove response mapping into EMI wallet/bank records and x-change provider account links without creating real wallets unless explicitly requested.

#### Layer 5 - Claim and issuance guards

Add scenarios:

```text
turnkey_issuer_blocks_missing_provider_wallet
turnkey_issuer_allows_ready_provider_wallet
turnkey_claim_blocks_missing_bank_account
turnkey_claim_resumes_after_provider_account_ready
```

These prove that provider readiness changes actual x-change behavior instead of being a passive record.

#### Layer 6 - Live provider opt-in

Live provider scenarios should be separate and explicit:

```text
provider_paynamics_wallet_live_provision
provider_paynamics_bank_account_live_link
provider_netbank_source_account_live_readiness
```

They should require an explicit runtime setting or command option such as:

```bash
php artisan xchange:lifecycle:run provider_paynamics_wallet_live_provision --live-provider --json
```

The command should refuse live provider scenarios unless `ProviderRuntimeSettingsResolverContract::allowsLiveProviderScenarios()` returns true.

### Credential-safe behavior

- use fake/null provider provisioning gateways by default in tests
- never require Paynamics OTP for no-payout readiness scenarios
- only hit live provider APIs when a scenario explicitly opts in with a flag or provider mode
- never print platform secrets in JSON output
- redact raw provider response metadata before persistence and output

Suggested checks:

```text
provider_topology resolves
provider provisioning gateway resolves
provider runtime settings resolver resolves
provider credentials/readiness config is discoverable without exposing secrets
owner link repository can write/read links
Paynamics gateway can map customer-wallet response
NetBank gateway can map ledger/bank readiness response
claim guard blocks when required link is missing
claim guard passes when required link is ready
```

## Implementation Slices

### Current Implementation Status

Last updated after Slice 10 live provider lifecycle verification.

The target ownership model is now implemented in x-change:

```text
x-change decides why account provisioning is needed.
onboarding records the user's readiness journey.
emi-core provides normalized EMI contracts and persistence primitives.
emi-netbank and emi-paynamics execute provider-specific API calls.
x-change stores owner-to-provider-account links and renders the product UX.
```

#### Completed slices

| Slice | Status | Implemented result |
| --- | --- | --- |
| Slice 1 - Rename the mental model | Done | The plan and code use provider account link/readiness language and avoid adding a duplicate x-change provider account table. |
| Slice 2 - Runtime settings resolver | Done | `ProviderRuntimeSettingsResolverContract`, config resolver, provider enablement, live-provider lifecycle opt-in, and doctor/runtime checks are in place. |
| Slice 3 - x-change provider account links | Done | `xchange_provider_account_links`, model, repository contract, Eloquent repository, bindings, and tests are in place. |
| Slice 4 - Provisioning modes and flow descriptors | Done | `ProviderProvisioningMode`, `ProvisioningFlowDescriptorData`, descriptor builder, and descriptor tests are in place. |
| Slice 5 - Provisioning manager and fake gateway | Done | Provisioning manager, gateway contract, fake/delegating gateway, bindings, and tests are in place. |
| Slice 6 - Lifecycle account-link verification | Done | Turnkey lifecycle scenarios now cover provider link persistence, pending blocks, ready passes, NetBank ledger/bank readiness, and Paynamics fake wallet/bank/link readiness. |
| Slice 7 - NetBank gateway | Done | NetBank provisioning gateway supports local ledger wallet readiness, bank-account readiness, and optional source-account readiness checks. |
| Slice 8 - Paynamics gateway | Done | Paynamics provisioning gateway maps fake and opt-in live customer-wallet, KYC, and bank-account responses into EMI records and x-change provider links. |
| Slice 9 - Claim/issuance guard | Done | Pay Code issuance and claim preparation use provider readiness guards and project provisioning requirements into the product UI. |
| Slice 10 - Live provider lifecycle verification | Done | Live-provider scenarios exist for Paynamics wallet provisioning, Paynamics bank-account linking, and NetBank source-account readiness; they require both runtime setting opt-in and `--live-provider`. |

#### Remaining non-blocking follow-ups

- Keep `spatie/laravel-settings` as an optional adapter until the host app needs an operator settings console.
- Run real live-provider lifecycle scenarios only in a configured environment with provider sandbox credentials.
- Expand browser/UI coverage around provider setup screens if the onboarding package adds richer provider-specific web surfaces.

### Slice 1 - Rename the mental model

Documentation and naming only:

- treat `emi-core.provider_accounts` as the canonical provider account table
- add x-change terminology: provider account link, owner link, readiness link
- avoid adding `LBHurtado\XChange\Models\ProviderAccount`

### Slice 2 - runtime settings resolver

Add:

- `ProviderRuntimeSettingsResolverContract`
- `ConfigProviderRuntimeSettingsResolver`
- config keys for provider enablement and live-provider lifecycle opt-in
- doctor check extension for runtime settings
- tests proving secrets are not exposed

Defer Spatie integration to an optional implementation unless the host app explicitly adopts `spatie/laravel-settings`.

### Slice 3 - x-change provider account links

Add:

- migration for `xchange_provider_account_links`
- `ProviderAccountLink` model
- `ProviderAccountLinkRepositoryContract`
- Eloquent repository
- service binding in `config/x-change.php`
- unit tests

### Slice 4 - provisioning modes and flow descriptors

Add:

- `ProviderProvisioningMode`
- `ProvisioningFlowDescriptorData`
- `BuildProvisioningFlowDescriptor`
- unit tests for Paynamics and NetBank descriptors

### Slice 5 - provisioning manager and fake gateway

Add:

- `ProviderProvisioningGatewayContract`
- `ProviderProvisioningManagerContract`
- `DefaultProviderProvisioningManager`
- fake/null gateway for tests and lifecycle no-provider mode
- tests for topology-to-mode mapping

### Slice 6 - lifecycle account-link verification

Add lifecycle scenarios before live provider integration:

- provider account link persistence
- pending link blocks
- ready link passes
- NetBank ledger/bank readiness with fakes
- Paynamics wallet response mapping with fakes

These scenarios should run under the existing `turnkey-onboarding` group or a new `turnkey-provider-provisioning` group.

### Slice 7 - NetBank gateway

Add:

- `NetbankProviderProvisioningGateway`
- local ledger wallet readiness
- bank-account readiness mapping
- optional source-account readiness check if a stable NetBank readiness API exists
- tests with fakes

Do not attempt per-user NetBank provider wallet creation in this slice.

### Slice 8 - Paynamics gateway

Add:

- `PaynamicsProviderProvisioningGateway`
- mapper from x-change/onboarding subject to `AddCustomerWallet` payload
- mapper from Paynamics response to `emi-core.wallets`
- optional `AddBankAccount` mapping
- optional KYC link mapping
- tests with fake Paynamics responses

Live Paynamics calls should remain opt-in.

### Slice 9 - claim/issuance guard

Add policy gates:

- issuer cannot issue when selected topology requires a provider wallet and no ready link exists
- redeemer claim blocks into onboarding when bank-account readiness is required
- claim resumes when onboarding is complete and the required link is ready

### Slice 10 - live provider lifecycle verification

Add lifecycle scenarios:

- live NetBank source-account readiness scenario
- live Paynamics wallet provisioning scenario
- live Paynamics bank account link scenario

These must be opt-in, disabled by default, and protected by runtime settings.

## Security Rules

Do not store these in onboarding sessions, x-change provider links, voucher metadata, frontend props, or logs:

- Paynamics username/password
- Paynamics merchant key
- NetBank client secret
- webhook signing secrets
- raw OTP
- raw bank password
- raw provider portal password

Allowed to persist:

- provider wallet id
- provider account id
- provider bank account id
- masked bank account number
- external uid
- verification status
- capture/KYC link when intended for the user
- redacted provider response metadata

Use provider package config as the source of platform credentials:

```text
Paynamics -> config('constellation.*')
NetBank   -> config('omnipay.*') and config('disbursement.*')
```

Use runtime settings only for operational switches and non-secret values unless the host explicitly chooses an encrypted settings implementation.

## Tests to Scaffold

### Unit tests

- provider topology maps Paynamics to `provider_customer_wallet`
- provider topology maps NetBank to `ledger_pooled`
- provisioning mode resolver maps Paynamics to wallet creation/resolution
- provisioning mode resolver maps NetBank to ledger/bank readiness
- flow descriptor returns Paynamics wallet steps
- flow descriptor returns NetBank bank-account steps
- provider account link repository stores ready Paynamics wallet link
- provider account link repository stores ready NetBank bank link

### Feature tests

- issuer provisioning starts when Paynamics wallet link is missing
- issuer issuance passes when Paynamics wallet link is ready
- NetBank issuer gets local ledger wallet through existing `WalletProvisioningContract`
- redeemer claim starts bank onboarding when bank-account readiness is required
- claim submit is blocked while required provider link is pending
- claim submit resumes when provider link is ready

### Security tests

- platform credentials are not serialized into onboarding responses
- platform credentials are not stored in provider account links
- raw OTP is not persisted in provider account links
- raw bank account number is masked unless an EMI provider explicitly owns encrypted storage
- provider metadata is redacted before persistence
- runtime settings resolver does not expose provider secrets
- lifecycle JSON output does not include provider secrets
- live provider scenarios refuse to run unless explicitly enabled

## Open Questions

1. Should x-change depend directly on `emi-core` models, or should it keep foreign IDs nullable and resolve EMI records only when `emi-core` is installed?
2. Should Paynamics customer-wallet creation be issuer-only first, or also available to redeemers?
3. Should bank-account readiness belong to the redeemer identity, the issuer identity, or both depending on voucher instruction?
4. Should the Paynamics customer wallet be funded by wallet-to-wallet transfer during issuer onboarding, or only by manual bank-to-bank funding for now?
5. Should onboarding completion trigger provisioning immediately, or should provisioning be a separate post-onboarding action with retry/audit UI?
6. Should `spatie/laravel-settings` be offered as an optional x-change install flag for operator-editable provider settings?
7. Should live provider lifecycle scenarios require both a config setting and a command option, or is one guard enough?

## Practical Recommendation

Start with the smallest useful slice:

```text
1. Add the runtime settings resolver boundary.
2. Add xchange_provider_account_links.
3. Add repository and fake provisioning gateway.
4. Add flow descriptors.
5. Add lifecycle scenarios proving link/readiness behavior.
6. Wire NetBank as ledger + bank-account readiness.
7. Wire Paynamics wallet provisioning with fake responses.
8. Wire live Paynamics only after the fake path, lifecycle scenarios, and UI are stable.
9. Keep Spatie settings optional until the host needs an editable provider settings console.
```

This keeps the x-change package turnkey while respecting the provider package boundaries already present in the codebase.
