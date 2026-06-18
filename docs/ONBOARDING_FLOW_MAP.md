# x-change Onboarding + Provisioning Flow Map

**Version**: 1.0
**Last Updated**: 2026-06-18
**Audience**: Human developers + AI agents

This document maps the installed x-change onboarding and account-provisioning experience — from package installation to mobile-first auth, provider setup prompts, onboarding completion, provider link creation, and guarded Pay Code flow resumption.

It is the companion map to `CLAIM_FLOW_MAP.md`. Read this when you need to understand what a host app should look like after install, where onboarding is surfaced in the UI, and how x-change hands off provider readiness work to onboarding and EMI packages.

The key idea is readiness, not voucher-owned onboarding:

```text
Issuer readiness before Pay Code issuance:
    system/provider settings + issuer wallet/provider account link must be valid.

Claimant readiness during Pay Code redemption:
    claimant destination/bank/provider readiness must be valid before payout can continue.

x-change decides why readiness is needed.
onboarding records the user's setup journey.
provider provisioning creates or refreshes the provider account link.
the original issuance or redemption flow resumes when the link is ready.
```

---

## Flow Diagram

```
┌──────────────────────── PHASE 0: INSTALL / PUBLISH ────────────────────────┐
│                                                                             │
│  composer require 3neti/x-change                                            │
│       │                                                                     │
│       ▼                                                                     │
│  php artisan x-change:install --force                                       │
│       │ publishes x-change UI, auth/settings scaffold, assets               │
│       │ publishes onboarding config + migrations when onboarding installed  │
│       │ publishes form-flow drivers and handler assets when installed       │
│       │ runs migrations unless --no-migrate                                 │
│       ▼                                                                     │
│  npm install && npm run build                                               │
│       │                                                                     │
│       ▼                                                                     │
│  php artisan x-change:doctor --json                                         │
│       │ checks onboarding package/config/table + provider runtime settings  │
│       ▼                                                                     │
│  php artisan xchange:lifecycle:run-group turnkey-onboarding                 │
│       │ proves mobile auth, onboarding, provider links, guards, fakes       │
│                                                                             │
└─────────────────────────────────┬───────────────────────────────────────────┘
                                  │
┌────────────────────── PHASE 1: MOBILE-FIRST HOST UX ───────────────────────┐
│                                  │                                          │
│  Published Fortify auth scaffold │                                          │
│       │                          ▼                                          │
│       │                    Login.vue / Register.vue                         │
│       │                    mobile + PIN identity                            │
│       │                    email optional by config                         │
│       ▼                                                                     │
│  Published settings scaffold                                                │
│       │ Profile.vue keeps mobile current                                    │
│       │ SecurityConfirm.vue stays inside /settings                         │
│       ▼                                                                     │
│  Authenticated user enters x-change UI                                      │
│                                                                             │
└─────────────────────────────────┬───────────────────────────────────────────┘
                                  │
             ┌────────────────────┴────────────────────┐
             │                                         │
             ▼                                         ▼
┌────────────── PHASE 2A: ISSUER READINESS BEFORE ISSUANCE ──────────────────┐
│                                                                             │
│  GET /x/pay-codes/create                                                    │
│       │                                                                     │
│       ▼                                                                     │
│  pay-codes/Create.vue                                                       │
│       │ normal form when issuer readiness passes                            │
│       │ ProvisioningSetup.vue when provider readiness is missing            │
│       ▼                                                                     │
│  GeneratePayCode action / API                                               │
│       │ ProviderReadinessGuard::evaluateIssuer(owner, provider)             │
│       │                                                                     │
│       ├─ NetBank topology: ledger_pooled                                    │
│       │     local ledger wallet readiness required                          │
│       │     no per-user NetBank provider wallet in current topology         │
│       │                                                                     │
│       └─ Paynamics topology: provider_customer_wallet                       │
│             provider customer wallet link required                          │
│             missing link throws ProviderProvisioningRequired                │
│                                                                             │
└─────────────────────────────────┬───────────────────────────────────────────┘
                                  │
                                  │ ProviderProvisioningRequired
                                  ▼
┌──────────── PHASE 2B: CLAIMANT READINESS DURING REDEMPTION ────────────────┐
│                                                                             │
│  GET /x/claim or POST /x/claim/{code}/submit                                │
│       │                                                                     │
│       ▼                                                                     │
│  claim/Entry.vue or claim submit flow                                       │
│       │ ProvisioningSetup.vue when bank/provider readiness is missing       │
│       ▼                                                                     │
│  SubmitPayCodeClaim action                                                  │
│       │ ProviderReadinessGuard::evaluateClaimant(owner, provider, context)  │
│       │                                                                     │
│       └─ Bank-account readiness required                                    │
│             start redemption onboarding                                     │
│             store onboarding reference in guarded flow metadata/session     │
│             block claim until onboarding completes and provider link ready  │
│                                                                             │
└─────────────────────────────────┬───────────────────────────────────────────┘
                                  │
                                  ▼
┌──────────────────── PHASE 3: PROVISIONING UI PROJECTION ───────────────────┐
│                                                                             │
│  BuildProvisioningRequirementViewData                                       │
│       │                                                                     │
│       ├─ reason / purpose / provider / mode / missing[]                     │
│       ├─ onboarding.reference                                               │
│       ├─ onboarding.links.status_url                                        │
│       ├─ onboarding.links.resume_url                                        │
│       └─ descriptor: ProvisioningFlowDescriptorData                         │
│             title, description, steps, fields, actions, metadata            │
│                                                                             │
│  ProvisioningSetup.vue                                                      │
│       │ badges: provider, mode, reference                                   │
│       │ renders descriptor-aware setup card                                 │
│       │ primary CTA: resume onboarding web surface                          │
│       │ secondary CTA: check status, then resume guarded flow if complete   │
│                                                                             │
└─────────────────────────────────┬───────────────────────────────────────────┘
                                  │
                                  ▼
┌───────────────────── PHASE 4: ONBOARDING PACKAGE WEB ──────────────────────┐
│                                                                             │
│  GET /onboarding/{reference}?return_url=/x/...                              │
│       │                                                                     │
│       ▼                                                                     │
│  onboarding::show Blade surface (temporary generic package surface)         │
│       │ displays onboarding reference, purpose, status, payload/context     │
│       │ can complete or cancel                                              │
│       │                                                                     │
│       ├─ POST /onboarding/{reference}/complete                              │
│       │     marks onboarding session completed                              │
│       │     redirects to safe return_url or onboarding page                 │
│       │                                                                     │
│       └─ POST /onboarding/{reference}/cancel                                │
│             marks onboarding session cancelled                              │
│             rejects unsafe return_url                                       │
│                                                                             │
└─────────────────────────────────┬───────────────────────────────────────────┘
                                  │
                                  ▼
┌────────────── PHASE 5: ONBOARDING COMPLETION HOOK / PROVISIONING ──────────┐
│                                                                             │
│  x-change decorates onboarding service                                      │
│       │ ProvisioningAwareOnboardingService::complete()                      │
│       ▼                                                                     │
│  StartProviderProvisioningFromOnboardingCompletion                          │
│       │ resolves onboarding owner + purpose/context                         │
│       │ calls ProviderProvisioningManager::startOrResume(owner, payload)    │
│       ▼                                                                     │
│  ProviderProvisioningManager                                                │
│       │ resolves provider from runtime settings / payload                   │
│       │ resolves topology and mode                                          │
│       │ delegates to configured provider gateway                            │
│       ▼                                                                     │
│  Provider gateway                                                           │
│       ├─ FakeProviderProvisioningGateway (default/test-safe)                │
│       ├─ NetbankProviderProvisioningGateway                                 │
│       │     ledger_wallet / bank_account_link / source-account readiness   │
│       └─ PaynamicsProviderProvisioningGateway                               │
│             wallet_create / wallet_resolve / bank_account_link / hybrid     │
│             fake by default, live only when runtime settings allow          │
│       ▼                                                                     │
│  ProviderAccountLinkRepository::storeFromProvisioningResult                 │
│       │ writes xchange_provider_account_links                               │
│       │ links owner -> EMI provider account / wallet / bank account         │
│       │ stores provider IDs, status, capabilities, redacted metadata        │
│                                                                             │
└─────────────────────────────────┬───────────────────────────────────────────┘
                                  │
                                  ▼
┌──────────────────── PHASE 6: GUARDED FLOW RESUMES ─────────────────────────┐
│                                                                             │
│  User returns to original x-change page                                     │
│       │                                                                     │
│       ├─ Issuance: Create.vue resubmits with onboarding reference           │
│       │     GeneratePayCode resumes provisioning from onboarding            │
│       │     readiness guard sees ready provider link                        │
│       │     Pay Code is generated                                           │
│       │                                                                     │
│       └─ Claim: Entry.vue / claim submit retries with onboarding reference  │
│             SubmitPayCodeClaim resumes provisioning from onboarding         │
│             readiness guard sees bank/provider readiness                    │
│             claim continues into normal claim execution                     │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Phase 0: Install / Publish

**Command**: `php artisan x-change:install --force`
**Location**: `packages/x-change/src/Console/Commands/InstallXChangeCommand.php`

The installer turns a Laravel host application into a bootable x-change surface. It publishes files because Laravel owns host auth, routes, Inertia pages, Vite assets, and the application `User` model.

### Published surfaces

| Publish area | What appears in the host | Why it matters |
|---|---|---|
| `x-change-ui` | x-change pages, components, layouts, composables | Makes `/x/pay-codes`, `/x/pay-codes/create`, `/x/claim`, setup cards, and product UI compile in host Vite |
| `x-change-auth` | mobile-first `User`, auth pages, migration | Makes Fortify registration/login use mobile + PIN |
| `x-change-auth-tests` | auth feature tests | Proves installed host login/register behavior |
| `x-change-settings` | profile/security settings scaffold | Keeps mobile number and PIN/security UX host-owned |
| `x-change-settings-tests` | settings feature tests | Proves mobile-first profile/security behavior |
| `x-change-assets` | public branding assets | Makes package UI render with x-change assets |
| `x-change-form-flow-drivers` | voucher redemption driver config | Keeps claim data collection compatible with form-flow |
| onboarding config/migrations | `config/onboarding.php`, `onboarding_sessions` table | Enables onboarding references and completion state |
| form-flow handler assets | KYC, OTP, selfie, signature, location assets when installed | Enables claim evidence collection |
| x-rider assets | rider UI/drivers when installed | Enables post-claim rider UX |

### Expected host boot sequence

```bash
composer require 3neti/x-change
php artisan x-change:install --force
npm install
npm run build
php artisan x-change:doctor --json
php artisan xchange:lifecycle:run-group turnkey-onboarding --no-claim --timeout=1 --poll=1 --max-polls=1
php artisan test
```

During active development, `npm run dev` can replace `npm run build`.

### Install-time diagnostic expectations

`x-change:doctor --json` should report:

- onboarding package is installed when expected
- onboarding config is loaded
- `onboarding_sessions` table exists
- provider runtime settings resolver is bound
- provider topology/runtime settings are discoverable without exposing platform secrets
- live-provider lifecycle scenarios remain disabled unless explicitly enabled

---

## Phase 1: Mobile-First Host UX

**Published pages**: host `resources/js/pages/auth/Login.vue`, `Register.vue`, settings pages
**Backend**: Fortify remains the auth backend; x-change changes the host scaffold and config expectations.

The planned and implemented host onboarding starts before provider setup. A new host user should first have a mobile-first account identity:

1. User registers with mobile + PIN.
2. Host `User` stores and normalizes mobile.
3. Email can remain optional depending on `x-change.onboarding.email_required`.
4. Settings profile keeps mobile current.
5. Auth/session behavior remains Fortify-native.

This matters because x-change provisioning payloads rely on an owner identity with a mobile channel. Paynamics wallet creation and NetBank readiness both need a stable owner.

---

## Phase 2A: Issuer Readiness Before Pay Code Issuance

**Route**: `GET /x/pay-codes/create`
**Vue**: `resources/js/pages/x-change/pay-codes/Create.vue`
**Gate**: issuer provider readiness

This phase is not "onboard a redeemer through a Pay Code." It means the issuer's account, provider configuration, and provider account link must be valid before x-change allows that issuer to create Pay Codes.

The normal issuer path is:

```
Issuer opens /x/pay-codes/create
  → Create.vue renders Pay Code form
  → issuer submits Generate Pay Code
  → GeneratePayCode evaluates issuer provider readiness
  → ready: Pay Code is generated
```

When readiness is missing:

```
GeneratePayCode
  → ProviderReadinessGuard::evaluateIssuer()
  → ProviderProvisioningRequired
  → session stores provisioning requirement
  → Create.vue renders ProvisioningSetup.vue
```

### Provider-specific issuer behavior

| Provider topology | Readiness requirement | User-visible result |
|---|---|---|
| `manual` | no provider-specific account link | Pay Code form continues normally |
| `netbank` / `ledger_pooled` | local ledger wallet readiness | setup can open/verify local wallet; no per-user NetBank wallet is created |
| `paynamics` / `provider_customer_wallet` | ready provider customer wallet link | setup asks user to create/resolve Paynamics wallet before issuance |

---

## Phase 2B: Claimant Readiness During Pay Code Redemption

**Route**: `GET /x/claim`, `GET /x/claim?code=...`, `POST /x/claim/{code}/submit`
**Vue**: `resources/js/pages/x-change/claim/Entry.vue`
**Gate**: claimant bank/provider readiness

This is the redeemer-side version of the same readiness idea. Claim onboarding is triggered when a Pay Code redemption requires destination/provider readiness that does not exist yet.

```
Claimant starts claim
  → claim data collected through claim/form-flow path
  → SubmitPayCodeClaim builds canonical claim payload
  → ProviderReadinessGuard::evaluateClaimant()
  → missing bank account/provider link
  → ProviderProvisioningRequired
  → Entry.vue renders ProvisioningSetup.vue
```

### Claim-specific behavior

| Situation | What x-change does | What claimant sees |
|---|---|---|
| no bank/provider setup needed | claim continues | normal claim flow |
| bank account required, missing | starts redemption onboarding | setup card with onboarding reference |
| onboarding completed, provider link still pending | blocks and asks user to check/resume | setup card remains |
| provider bank-account link ready | claim resumes | normal claim execution continues |

---

## Phase 3: Provisioning UI Projection

**Component**: `resources/js/components/x-change/ProvisioningSetup.vue`
**DTO**: `LBHurtado\XChange\Data\ProvisioningFlowDescriptorData`
**Builder**: `LBHurtado\XChange\Services\BuildProvisioningFlowDescriptor`
**View data**: `LBHurtado\XChange\Services\BuildProvisioningRequirementViewData`

The Vue UI does not hardcode Paynamics or NetBank setup copy. x-change projects provider-aware setup metadata into the frontend.

### Provisioning requirement shape

```php
[
    'reason' => '...',
    'purpose' => 'IssuePayCode|RedeemPayCode|BankOnboardingRequired',
    'provider' => 'paynamics|netbank|manual',
    'mode' => 'wallet_create|bank_account_link|ledger_wallet|...',
    'missing' => ['provider_customer_wallet', 'bank_account_link'],
    'onboarding' => [
        'reference' => '...',
        'links' => [
            'status_url' => '/api/onboarding/{reference}',
            'resume_url' => '/onboarding/{reference}',
        ],
    ],
    'descriptor' => [
        'title' => '...',
        'description' => '...',
        'steps' => ['profile', 'wallet', 'kyc', 'ready'],
        'fields' => ['mobile', 'name', 'email'],
        'actions' => ['continue', 'open_capture_link'],
        'metadata' => [],
    ],
]
```

### Current descriptor expectations

| Provider + mode | UI title | Expected steps |
|---|---|---|
| Paynamics `wallet_create` / `wallet_resolve` | Create your Paynamics wallet | profile → wallet → KYC link → ready |
| Paynamics `bank_account_link` | Add your payout bank account | bank account → consent → provider bind → ready |
| NetBank `bank_account_link` | Add payout destination | bank account → consent → ready |
| fallback/manual | Complete provider setup | setup → ready |

### ProvisioningSetup.vue behavior

The setup card shows:

- provider and mode badges
- onboarding reference badge when available
- descriptor title and description
- setup steps from `descriptor.steps`
- required fields from `descriptor.fields`
- missing readiness keys from `missing[]`
- primary CTA to onboarding `resume_url`
- secondary CTA to `status_url`

When the user clicks **Check setup status**:

```
ProvisioningSetup.vue
  → fetch(onboarding.links.status_url)
  → if completed: emit resume
  → if pending: show "still in progress"
  → if cancelled: show cancelled warning
```

When the user clicks **Resume flow**:

```
ProvisioningSetup.vue
  → opens /onboarding/{reference}?return_url={current x-change URL}
```

---

## Phase 4: Onboarding Package Web Surface

**Package**: `3neti/onboarding`
**Routes**:

| Route | Name | Purpose |
|---|---|---|
| `GET /onboarding/{reference}` | `onboarding.web.show` | show/resume onboarding |
| `POST /onboarding/{reference}/complete` | `onboarding.web.complete` | mark onboarding complete |
| `POST /onboarding/{reference}/cancel` | `onboarding.web.cancel` | cancel onboarding |
| `GET /api/onboarding/{reference}` | `onboarding.show` | JSON status |
| `POST /api/onboarding/{reference}/complete` | `onboarding.complete` | API completion |
| `POST /api/onboarding/{reference}/cancel` | `onboarding.cancel` | API cancellation |

The current onboarding web surface is intentionally generic. It is a package-owned Blade page that displays the onboarding reference, purpose, status, and context/result data, and provides complete/cancel actions.

### Why this page is Blade, not Vue

x-change product screens are Vue/Inertia because they are published into the host application's `resources/js` tree and compiled by the host Vite build.

The onboarding package is intentionally more generic. It should be usable by Laravel hosts that do not run the x-change Vue/Inertia UI. For that reason, the first onboarding web surface is a self-contained Blade fallback:

- no host Vite build required
- no x-change Vue dependency required
- package can expose `/onboarding/{reference}` immediately after install
- enough UI exists to prove the orchestration handoff from x-change to onboarding and back

That Blade page is not the final product UX. It is the generic package-owned resume/completion surface. A richer host-specific implementation can replace it later with a Vue/Inertia onboarding page as long as it keeps the same contract:

```text
GET /onboarding/{reference}
POST /onboarding/{reference}/complete
POST /onboarding/{reference}/cancel
safe return_url back to the guarded x-change flow
```

### What the reader should expect

```
/onboarding/{reference}
  → shows current onboarding state
  → allows completion for fake/manual flows
  → redirects to safe return_url when provided
```

This is the first real web entry/resume surface. It is enough to prove the orchestration handoff:

```text
x-change guarded flow
  → onboarding reference created
  → user opens onboarding web surface
  → onboarding completed
  → x-change completion hook starts/resumes provider provisioning
  → user returns to guarded x-change flow
```

Future richer onboarding pages can replace or extend this generic surface without moving provider API logic into the onboarding package.

---

## Phase 5: Onboarding Completion Hook / Provider Provisioning

**Decorator**: `LBHurtado\XChange\Services\ProvisioningAwareOnboardingService`
**Hook**: `LBHurtado\XChange\Services\StartProviderProvisioningFromOnboardingCompletion`
**Manager**: `LBHurtado\XChange\Services\DefaultProviderProvisioningManager`

When onboarding completes, x-change starts or resumes provider provisioning.

```
OnboardingServiceContract::complete(reference, payload)
  → ProvisioningAwareOnboardingService::complete()
  → StartProviderProvisioningFromOnboardingCompletion
  → ProviderProvisioningManager::startOrResume(owner, payload)
  → ProviderProvisioningGatewayContract::provision(owner, payload)
  → ProviderAccountLinkRepository::storeFromProvisioningResult(owner, result)
```

### Package ownership boundary

| Package | Owns |
|---|---|
| `x-change` | why provisioning is required, guarded product flow, provider topology, UI projection, owner-to-provider links |
| `onboarding` | reference, status, completion/cancellation journey |
| `emi-core` | normalized provider accounts, wallets, bank accounts, EMI persistence primitives |
| `emi-netbank` | NetBank API/config/payment gateway details |
| `emi-paynamics` | Paynamics Constellation actions and provider-specific API details |

### Provider gateway behavior

| Gateway | Safe default | Live behavior |
|---|---|---|
| `FakeProviderProvisioningGateway` | returns ready/pending fake provider links | none |
| `NetbankProviderProvisioningGateway` | local wallet and destination bank readiness | optional source-account readiness when configured |
| `PaynamicsProviderProvisioningGateway` | fake wallet/KYC/bank responses mapped into EMI records | opt-in live AddCustomerWallet/AddBankAccount/GetWalletDetails actions |

Platform provider credentials must stay in provider package config (`constellation`, `omnipay`, `disbursement`) and must not be serialized into onboarding sessions, provider links, frontend props, or lifecycle output.

---

## Phase 6: Provider Account Link Persistence

**Table**: `xchange_provider_account_links`
**Model**: `LBHurtado\XChange\Models\ProviderAccountLink`
**Repository**: `ProviderAccountLinkRepositoryContract`

x-change does not duplicate EMI provider account tables. It stores an owner-readiness link that points to EMI records when they exist.

### Link stores

- owner morph
- provider (`paynamics`, `netbank`, `manual`)
- topology (`provider_customer_wallet`, `ledger_pooled`, `manual`)
- purpose (`IssuePayCode`, `RedeemPayCode`, `BankOnboardingRequired`)
- provisioning mode
- EMI provider account / wallet / bank-account IDs
- provider account / wallet / bank-account identifiers
- external UID
- status and verification status
- capabilities
- redacted metadata
- ready timestamp

### Readiness lookup

```
ProviderReadinessGuard
  → ProviderAccountLinkRepository::findReadyForOwner(owner, provider, mode)
  → ready link found: allow guarded flow
  → no ready link: start onboarding/provisioning requirement
```

---

## Phase 7: Guarded Flow Resumption

Once onboarding and provisioning are complete, the user returns to the original x-change surface.

### Issuance resume

```
Create.vue
  → resubmit Pay Code request with onboarding.reference
  → GeneratePayCode::resumeProviderProvisioningFromOnboarding()
  → guard checks provider link
  → GenerateVouchers runs
  → Pay Code created
```

Expected UI:

- setup card disappears after ready state
- Pay Code form is usable
- generated Pay Code appears in the normal pay-code registry/detail flow

### Claim resume

```
Entry.vue / ClaimSubmitController
  → retry claim with onboarding_reference
  → SubmitPayCodeClaim::resumeProviderProvisioningFromOnboarding()
  → guard checks bank/provider readiness
  → claim execution continues
```

Expected UI:

- setup card disappears after ready state
- claim continues into normal claim execution
- if provider payout OTP is required, the flow pauses at the payout authorization gate, not onboarding

---

## Provider Branches

### Manual provider

```
manual topology
  → no provider API account creation
  → fake/manual readiness link if needed
  → guarded flow resumes
```

Manual mode is useful for demos, local development, and host installs that are not ready to use a live provider.

### NetBank provider

```
netbank topology: ledger_pooled
  → issuer source of truth: local ledger wallet
  → provider source account: configured NetBank operator account
  → per-user provider wallet: not created in current topology
  → claimant destination: collected bank account readiness
```

Expected provisioning modes:

- `ledger_wallet` for issuer/local wallet readiness
- `bank_account_link` for claimant destination readiness

Live source-account readiness is available only through explicit lifecycle verification and provider runtime settings.

### Paynamics provider

```
paynamics topology: provider_customer_wallet
  → issuer/customer needs provider wallet
  → optional KYC/KYB capture link
  → optional bank-account binding
  → provider IDs mapped to emi-core wallets/bank_accounts
  → x-change provider link marks readiness
```

Expected provisioning modes:

- `wallet_create`
- `wallet_resolve`
- `bank_account_link`
- `hybrid`

Fake Paynamics responses are used by default for safe lifecycle and tests. Live Paynamics calls are opt-in.

---

## Lifecycle Verification

The onboarding/provisioning path is accepted through executable lifecycle scenarios, not just static documentation.

### Safe turnkey group

```bash
php artisan xchange:lifecycle:run-group turnkey-onboarding --no-claim --timeout=1 --poll=1 --max-polls=1
```

Expected scenario coverage:

| Scenario | Proves |
|---|---|
| `turnkey_mobile_boot` | mobile-first host auth/onboarding surface resolves |
| `turnkey_bank_onboarding_required` | redemption onboarding requirement maps correctly |
| `turnkey_provider_link_ready` | ready provider links can be created and resolved |
| `turnkey_provider_link_pending_blocks` | pending provider links do not satisfy readiness |
| `turnkey_netbank_ledger_wallet_ready` | NetBank ledger-wallet readiness works |
| `turnkey_netbank_bank_account_ready` | NetBank bank-account readiness works |
| `turnkey_paynamics_wallet_fake_provisioned` | fake Paynamics wallet response maps to EMI + x-change link |
| `turnkey_paynamics_bank_account_fake_linked` | fake Paynamics bank-account response maps to EMI + x-change link |
| `turnkey_paynamics_wallet_link_ready` | Paynamics wallet provisioning creates a ready owner link |
| `turnkey_issuer_blocks_missing_provider_wallet` | issuance blocks when Paynamics wallet is missing |
| `turnkey_issuer_allows_ready_provider_wallet` | issuance resumes when Paynamics wallet link is ready |
| `turnkey_claim_blocks_missing_bank_account` | claim blocks when bank readiness is missing |
| `turnkey_claim_resumes_after_provider_account_ready` | claim resumes when bank/provider readiness is ready |

### Live provider verification

Live provider lifecycle scenarios are disabled by default and require both:

1. runtime setting: `x-change.provider_runtime.lifecycle.allow_live_provider_scenarios = true`
2. command option: `--live-provider`

```bash
php artisan xchange:lifecycle:run provider_paynamics_wallet_live_provision --live-provider --json
php artisan xchange:lifecycle:run provider_paynamics_bank_account_live_link --live-provider --json
php artisan xchange:lifecycle:run provider_netbank_source_account_live_readiness --live-provider --json
```

The command must refuse to run these scenarios without both safeguards.

---

## Security Rules

Never persist or expose:

- Paynamics username/password
- Paynamics merchant key
- NetBank client secret
- webhook signing secrets
- raw OTP
- raw bank password
- raw provider portal password
- raw provider request/response payloads that contain secrets

Allowed to persist and expose when appropriate:

- provider wallet ID
- provider account ID
- provider bank-account ID
- masked bank account number
- external UID
- verification status
- user-facing KYC/capture link
- redacted provider metadata

---

## Operator Expectations

After install and build, an operator should be able to:

1. Register/login using mobile-first auth.
2. Open `/x/pay-codes`.
3. Create a Pay Code if provider readiness is satisfied.
4. See a provider setup card if readiness is missing.
5. Follow the setup CTA to `/onboarding/{reference}`.
6. Complete onboarding.
7. Return to the guarded x-change page.
8. Retry issuance or claim.
9. Observe that provider links now drive readiness.
10. Prove the whole path with `xchange:lifecycle:run-group turnkey-onboarding`.

If the UI does not reflect newly published Vue changes, run:

```bash
npm run build
```

or in development:

```bash
npm run dev
```
