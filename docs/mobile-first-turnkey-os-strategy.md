# Mobile-First Turnkey X-Change OS Strategy

## Vision

x-change should be installable into a Laravel host as a turnkey, mobile-first Pay Code operating surface.

The host app should feel like a bootable x-change implementation, not a generic Laravel starter kit with x-change bolted on later.

Core product stance:

- mobile is the primary identifier;
- email is optional profile/contact metadata;
- onboarding prepares capability;
- x-change decides why capability is needed;
- provider topology and Pay Code money rules belong in x-change.

The desired host posture is:

```text
composer install
php artisan x-change:install
php artisan migrate
npm run build/dev

→ mobile-first x-change app
→ issuer can onboard
→ issuer can create Pay Codes
→ redeemer can claim
→ lifecycle scenarios can prove the whole path
```

## Signup And Authentication Strategy

The default signup page should not ask for mobile, OTP, PIN, password, name, and email all at once.

The recommended flow is:

```text
1. Enter mobile number
2. Verify OTP
3. Set 6-digit PIN
4. Complete issuer onboarding
5. Land on /x/pay-codes
```

The first registration screen should be intentionally small:

```text
Mobile number
Continue
```

Email should be optional and can be added later in profile or onboarding. Password should not be required for normal issuer/redeemer accounts in the first mobile-first path.

### Returning Login

Recommended returning login behavior:

```text
trusted device      → PIN
new device          → mobile OTP, then PIN
sensitive action    → PIN or OTP step-up
recovery/device swap → OTP plus risk checks, KYC, or support flow
admin/high risk     → optional password, passkey, or TOTP later
```

For the first turnkey slice, OTP can use the onboarding package auth enforcement seam with scaffold/null verification. Production SMS OTP can be introduced later without changing the user journey.

### PIN Rules

PIN is acceptable only if treated as a real secret:

- use 6 digits as the minimum;
- hash it like a password;
- block obvious values such as `000000`, `111111`, and `123456`;
- rate-limit attempts;
- lock or require step-up after repeated failures;
- do not allow PIN-only login on a new or untrusted device;
- never store the raw PIN.

### Rationale

Wallet-like products usually treat mobile number as the identity handle. OTP proves control of the mobile number. PIN is better than a password for frequent returning mobile use because it is short, repeatable, and familiar in wallet contexts.

Password at first signup adds friction and weakens the mobile-only positioning. Password, passkey, and TOTP should remain available as later higher-assurance options for administrators, operators, or high-risk accounts.

SMS OTP should be treated as a replaceable, risk-aware authenticator. It is useful for reach and bootstrapping, but the architecture should keep room for stronger factors later.

## Onboarding Boundary

`3neti/onboarding` owns onboarding capability.

It should answer:

```text
Who is the subject?
What identity level is required?
What onboarding steps are required?
What authentication assurance is required?
Has onboarding been completed?
```

`3neti/onboarding` owns:

- onboarding sessions;
- identity level;
- auth requirement policy;
- readiness guard;
- Contact to User promotion seam;
- wallet and bank provisioning seams.

x-change owns Pay Code intent and money orchestration.

x-change owns:

- Pay Code issuance;
- redemption policy;
- provider topology;
- provider selection for Pay Code flows;
- issuer and redeemer UX;
- lifecycle scenarios;
- when onboarding is required for a Pay Code.

The integration rule:

```text
onboarding prepares capability
x-change decides why capability is needed
provider packages execute provider APIs
```

## Provider Topology Strategy

x-change currently needs to support at least two provider setups. These should be explicit topologies in x-change, not hidden inside onboarding.

### Ledger-Pooled Provider

Example: NetBank-style setup.

Characteristics:

- one operational provider-side account or bank account;
- users do not each receive provider credentials;
- x-change uses `3neti/wallet` heavily;
- local wallet ledger is the source of truth for user balances;
- provider is a disbursement or settlement rail.

Suggested topology key:

```text
ledger_pooled
```

In this topology:

```text
issuer/user balance → local 3neti/wallet
Pay Code cost       → local ledger debit
payout              → provider rail execution
reconciliation      → provider result checked against local intent
```

### Provider-Custodial Customer Wallet

Example: Paynamics Constellation-style setup.

Characteristics:

- provider has a master or merchant wallet;
- each x-change user maps to a provider customer wallet;
- users interact with their provider customer wallet exclusively;
- each user may need provider wallet references or credentials;
- x-change still records local ledger/audit state.

Suggested topology key:

```text
provider_customer_wallet
```

In this topology:

```text
issuer/user funding source → provider customer wallet
local x-change ledger      → audit, fees, pricing, internal accounting
payout                     → provider wallet cash-out/disbursement
issuer OTP                 → provider-side authorization
reconciliation             → provider result plus local state
```

### Manual/Fake Provider

The turnkey OS needs a credential-free path for local development and lifecycle smoke.

Suggested topology key:

```text
manual
```

This topology should be the default for safe lifecycle scenarios.

## Provider Topology Boundary

Provider topology belongs in x-change.

Onboarding may provision or validate a capability through contracts, but it should not know Pay Code provider money rules.

Provider packages should own low-level API calls:

- `emi-paynamics` owns Constellation API calls;
- NetBank adapter owns NetBank API calls;
- x-change owns how those providers participate in Pay Code issuance, redemption, wallet settlement, and reconciliation.

Suggested future x-change contract:

```php
interface XChangeProviderTopologyContract
{
    public function key(): string;

    public function provisionIssuer(mixed $issuer, array $context = []): mixed;

    public function resolveFundingSource(mixed $issuer, array $context = []): mixed;

    public function requiresProviderCredentialsPerUser(): bool;

    public function usesLocalLedgerAsSourceOfTruth(): bool;
}
```

Suggested strategies:

```text
ManualProviderTopology
LedgerPooledProviderTopology
ProviderCustomerWalletTopology
```

Suggested config:

```php
'providers' => [
    'default' => env('XCHANGE_PROVIDER_TOPOLOGY', 'manual'),

    'topologies' => [
        'manual' => ManualProviderTopology::class,
        'netbank' => LedgerPooledProviderTopology::class,
        'paynamics' => ProviderCustomerWalletTopology::class,
    ],
],
```

## Turnkey User Journey

### First Boot

```text
Host installs x-change and onboarding
    → x-change publishes mobile-first auth scaffold
    → x-change publishes onboarding config/migrations
    → host runs migrations and builds assets
    → app opens as a mobile-first x-change surface
```

### Issuer Registration

```text
Issuer opens signup
    → enters mobile
    → verifies OTP
    → sets 6-digit PIN
    → onboarding starts issue_pay_code purpose
    → x-change provisions money capability based on provider topology
    → issuer lands on /x/pay-codes
```

For NetBank-like topology:

```text
issuer capability → local 3neti/wallet platform wallet
```

For Paynamics-like topology:

```text
issuer capability → provider customer wallet reference/credentials
local capability  → x-change ledger/audit records
```

### Pay Code Issuance

```text
Issuer creates Pay Code
    → x-change checks issuer onboarding readiness
    → x-change resolves provider topology
    → x-change resolves funding source
    → x-change issues voucher
    → registry shows Pay Code
```

### Normal Redeemer Claim

```text
Redeemer opens /x/claim/{code}
    → mobile is collected or confirmed
    → no onboarding requirement exists
    → x-change submits claim
    → provider disbursement or local redemption runs
    → success/rider flow proceeds
```

### Onboarding-Required Redeemer Claim

```text
Redeemer opens /x/claim/{code}
    → x-change detects bank_onboarding = required
    → x-change starts onboarding with bank_onboarding_required purpose
    → redeemer completes onboarding
    → x-change receives onboarding_reference
    → OnboardingGuard verifies readiness
    → x-change submits claim/disbursement
    → success/rider flow proceeds
```

### Paynamics OTP Claim

```text
Redeemer submits claim
    → Paynamics requires issuer-side payout OTP
    → redeemer waits on approval page
    → issuer opens /x/pay-codes/{code}/approval
    → issuer enters provider OTP
    → x-change replays/completes claim
    → redeemer reaches success/rider flow
    → issuer returns to Pay Code surface
```

## Voucher Instruction Shape

Onboarding requirements belong in x-change voucher instructions, not inside onboarding.

Recommended shape:

```php
'disbursement' => [
    'bank_onboarding' => 'none', // none|optional|required
    'target' => 'wallet',        // wallet|bank|auto
],
```

Interpretation:

```text
none:
    no onboarding fork

optional:
    user may choose normal claim or wallet/bank onboarding route

required:
    crossed Pay Code or bank/wallet-only flow
    onboarding_reference must be ready before disbursement
```

Claim payload extension:

```php
'onboarding_reference' => 'ONB-...',
```

## Lifecycle Scenario Runner Strategy

Lifecycle scenarios should prove the turnkey OS after installation. They should not perform dependency installation.

Lifecycle runner must not run:

```text
composer require
npm install
npm run build
destructive setup unless explicitly requested by existing --fresh/--prepare behavior
```

Safe lifecycle scenarios should assume the app is already installed and configured.

### Safe Credential-Free Scenarios

Add group/tag:

```text
turnkey_onboarding
```

Recommended safe scenarios:

```text
turnkey_mobile_boot
turnkey_issue_pay_code
turnkey_basic_cash_mobile
turnkey_bank_onboarding_required
```

These scenarios should use the manual/fake topology and require no NetBank or Paynamics credentials.

### Provider-Specific Scenarios

Recommended opt-in scenarios:

```text
turnkey_netbank_ledger_pooled
turnkey_paynamics_customer_wallet
turnkey_paynamics_otp_with_onboarding
```

Provider-specific scenarios should:

- require explicit provider selection or topology config;
- check credential readiness before doing provider calls;
- skip or fail fast with clear messages when credentials are missing;
- never silently fall back to manual provider.

Example commands:

```bash
php artisan xchange:lifecycle:run turnkey_mobile_boot --prepare
php artisan xchange:lifecycle:run turnkey_basic_cash_mobile --timeout=180 --poll=10
php artisan xchange:lifecycle:run turnkey_bank_onboarding_required --timeout=180 --poll=10
```

Provider examples:

```bash
php artisan xchange:lifecycle:run turnkey_netbank_ledger_pooled --provider=netbank --timeout=180 --poll=10
php artisan xchange:lifecycle:run turnkey_paynamics_customer_wallet --provider=paynamics --timeout=180 --poll=10
php artisan xchange:lifecycle:run turnkey_paynamics_otp_with_onboarding --provider=paynamics --approval-pipeline --timeout=180 --poll=10
```

Runner output should include:

- onboarding reference;
- onboarding purpose;
- subject mobile;
- onboarding status;
- provider topology key;
- local wallet id and balance when ledger-backed;
- provider customer wallet id/reference when provider-custodial;
- readiness skip reason when provider credentials are absent.

## Install And Doctor Strategy

`x-change:install` should publish the turnkey surface. It should not run dependency installation.

Responsibilities:

- publish x-change UI/assets;
- publish onboarding config/migrations when onboarding package is installed;
- publish mobile-first auth scaffold unless disabled;
- run migrations unless disabled.

Suggested options:

```text
--no-auth
--no-migrate
--no-assets
--no-handlers
--no-rider
```

Add a future `x-change:doctor` command for readiness checks.

Doctor should report:

- onboarding package installed;
- onboarding config present;
- onboarding sessions table exists;
- mobile-first user columns present;
- email nullable status;
- Fortify username/mobile config;
- x-change routes registered;
- provider topology selected;
- manual topology readiness;
- NetBank credential readiness;
- Paynamics credential readiness;
- lifecycle scenario readiness.

Provider credentials are not required for safe turnkey scenarios. They are required only for provider-specific scenarios.

## Implementation Slices

### Slice 1: Documentation And Contracts

- add this strategy document;
- add provider topology contract;
- add onboarding gateway contract;
- add config shape;
- no behavior change yet.

### Slice 2: Mobile-First Host Scaffold

- publish user migration additions;
- publish mobile-first Fortify registration/login scaffolding;
- make email optional;
- support generated password for compatibility;
- add tests for mobile-only registration.

### Slice 3: Onboarding Gateway

- add onboarding dependency;
- implement gateway backed by `OnboardingServiceContract` and `OnboardingGuard`;
- wire issuer onboarding to `issue_pay_code`;
- keep legacy issuer onboarding fallback.

### Slice 4: Provider Topologies

- add manual topology;
- add ledger-pooled topology;
- add provider-customer-wallet topology;
- wire NetBank and Paynamics config to topology keys.

### Slice 5: Redemption Onboarding Guard

- add `onboarding_reference` support to claim payload;
- enforce required onboarding based on voucher instructions;
- keep normal claims unchanged.

### Slice 6: Lifecycle And Doctor

- add safe turnkey lifecycle scenarios;
- add provider-specific lifecycle scenarios;
- add provider readiness checks;
- add `x-change:doctor`.

## Testing Strategy

Backend tests:

- mobile-only issuer onboarding succeeds with email omitted;
- onboarding-backed issuer flow uses `issue_pay_code`;
- required redemption onboarding rejects missing reference;
- required redemption onboarding rejects pending/cancelled references;
- completed onboarding allows claim continuation;
- no-onboarding vouchers continue unchanged;
- NetBank topology reports ledger-pooled behavior;
- Paynamics topology reports provider-customer-wallet behavior;
- manual topology requires no external credentials.

Frontend tests:

- signup page requires mobile only;
- OTP step follows mobile entry;
- PIN step follows OTP verification;
- email is optional;
- claim UI can represent onboarding-required vouchers;
- existing Paynamics issuer OTP approval UX remains intact.

Lifecycle tests:

- `turnkey_mobile_boot` proves mobile-only issuer boot;
- `turnkey_basic_cash_mobile` proves normal Pay Code issue/redeem;
- `turnkey_bank_onboarding_required` proves onboarding guard/reference injection;
- provider scenarios skip clearly when credentials are missing;
- Paynamics OTP scenario remains opt-in with `--approval-pipeline`.

## Key Decisions

- Mobile required, email optional.
- Signup starts with mobile only.
- OTP verifies mobile control.
- 6-digit PIN is the default returning-user secret.
- Password is not required by default for normal users.
- Production SMS OTP is not required in the first slice.
- Provider topology belongs in x-change, not onboarding.
- Lifecycle scenarios must be safe and credential-free by default.
- Provider scenarios are explicit and credential-gated.
