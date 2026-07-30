# Onboarding Voucher Compass

## Mission

Make recipient onboarding a first-class, priced Voucher instruction that is
compiled into an explicit claim workflow and executed through the existing
Voucher execution engine.

The target flow is:

```text
VoucherInstructionsData(onboarding: true)
    ↓ issuance normalization
execution.driver: onboarding_account_provisioning
    ↓ claim workflow resolution
onboarding.account-provisioning.v1
    ↓ form-flow mutation
Name + Email + Mobile + effective OTP policy
    ↓ execution engine
Idempotent user and Account provisioning
    ↓ successful completion
Authenticated claimant handoff to Cockpit
```

## Current Position

Current wave: Onboarding Voucher Revised Claim Architecture
Current slice: Lifecycle Treasury Isolation Correction
Status: Slices 0–9 complete
Last updated: 2026-07-30

| Slice | Name | Status |
|---|---|---|
| 0 | Compass and Boundary Lock | Completed |
| 1 | Minimal Voucher Instruction Contract | Completed |
| 2 | Commercial Catalog and Instruction Product | Completed |
| 3 | Explicit Claim Workflow Descriptor | Completed |
| 4 | Generic Claim Authentication Handoff | Completed |
| 5 | Account-Provisioning Execution Driver | Completed |
| 6 | Issuance Dependency UX | Completed |
| 7 | Template and Campaign Propagation | Completed |
| 8 | Lifecycle and Browser Acceptance | Completed |
| 9 | Lifecycle Treasury Isolation Correction | Completed |

## Settled Decisions

### Instruction contract

- `VoucherInstructionsData` owns one additive authoring flag:
  `onboarding`, defaulting to `false`.
- The flag declares intent. It does not embed routes, controller names,
  authentication implementation, or account-provisioning policy.
- Explicit legacy `claim.onboarding.mode=required` remains readable during the
  compatibility window. Legacy `if_required` is not silently reclassified.

### Execution and claim workflow

- Issuance normalization compiles `onboarding: true` into the explicit
  execution driver `onboarding_account_provisioning`.
- Claim UI resolves from persisted Voucher metadata through
  `DefaultClaimWorkflowResolver`.
- The canonical claim workflow key is
  `onboarding.account-provisioning.v1`.
- Vue must not infer onboarding from route names, Pay Code prefixes, amount,
  or guessed field combinations.
- `FormFlowClaimWorkflowMutator` carries the descriptor into
  `instructions.metadata.claim_workflow` and
  `steps[*].config.claim_workflow`.
- Canonical links remain `/x/claim/{code}`.

### Claim requirements

- The first onboarding workflow collects Full Name, Email, and Mobile.
- It does not collect an editable amount or an external payout destination.
- OTP is an authentication safeguard, not claimant profile data.
- `XCHANGE_ONBOARDING_REQUIRE_OTP` defaults to `true`.
- The sandbox may set it to `false` for local testing.
- A specific-mobile restriction always forces OTP, even when the local
  onboarding override is disabled.
- Production diagnostics must fail closed when onboarding OTP is disabled.

### Authentication

- Campaign officer authorization and claimant onboarding use a generic claim
  authentication-intent contract with distinct modes.
- Campaign authorization remains `authenticated_officer`.
- Onboarding uses `claimant_handoff`.
- Successful manual authentication regenerates the session identifier.
- OTPs, PINs, and raw identity evidence never enter Voucher metadata, claim
  workflow descriptors, or journal payloads.

### Commercial ownership

- `3neti/x-commerce` owns the immutable, versioned
  `onboarding.enabled` catalog item.
- `3neti/instruction` owns the corresponding purchasable instruction-product
  projection.
- `x-change` selects and snapshots the catalog line.
- The onboarding tariff is additive to explicitly required Name, Email,
  Mobile, OTP, and ordinary issuance lines.
- A missing onboarding price fails closed; it never becomes a silent
  zero-price instruction.

## Package Boundaries

| Package | Responsibility |
|---|---|
| `3neti/voucher` | Minimal instruction flag and execution-driver runtime seam |
| `3neti/x-commerce` | Canonical versioned onboarding price |
| `3neti/instruction` | Instruction product and revenue projection |
| `3neti/onboarding` | Identity and account-provisioning capability |
| `3neti/x-change` | Normalization, claim compilation, orchestration, Cockpit UX, and authenticated handoff |

The host application may provide environment configuration and published
assets. It does not own onboarding business logic.

## Invariants

1. Ordinary disbursement behavior remains unchanged.
2. Campaign officer authorization behavior remains unchanged.
3. Unknown onboarding execution drivers fail before mutation.
4. Existing users are reused; retries cannot create duplicate users or
   Accounts.
5. A failed economic execution does not consume the Voucher.
6. Disabled browser controls are never the enforcement boundary.
7. Pricing, persisted instructions, preview, claim compilation, and execution
   consume the same normalized dependency policy.
8. Authentication occurs only after the workflow reaches its authorized
   handoff point.

## Slice Log

### 2026-07-30 — Slice 14 completed

- The same authored-Rider policy now governs preview, success, and redirect
  surfaces.
- A neutral onboarding Voucher renders the ordinary claim success experience
  without inheriting a host demonstration message or redirect.
- Authored onboarding Rider content remains supported, and ordinary Voucher
  Rider resolution remains unchanged.

### 2026-07-30 — Slice 13 completed

- Browser forensics confirmed the Voucher persisted a neutral Rider, while
  the public preview API still resolved the host's demonstration Rider as a
  fallback.
- Onboarding previews now suppress that fallback only when the execution
  driver is `onboarding_account_provisioning` and no Rider content was
  authored.
- An onboarding Voucher with an intentional message, URL, Splash, or stage
  collection still receives the normal x-rider experience.
- Ordinary Voucher previews and authored Rider behavior remain unchanged.

### 2026-07-30 — Slice 12 completed

- In-app browser inspection found that the onboarding lifecycle inherited
  generic demonstration Rider content from the lifecycle defaults.
- The scenario now declares a neutral Rider explicitly, so the canonical
  onboarding walkthrough cannot display demo copy, artwork, or outbound links.
- Regression coverage verifies the persisted onboarding Voucher contains no
  Rider message, URL, or Splash.

### 2026-07-30 — Slice 11 completed

- Recast the lifecycle as a system-issued onboarding invitation while keeping
  its test money isolated from Treasury and provider Inventory.
- Added a dedicated `lifecycle` compatibility wallet for the system Account;
  lifecycle preparation tops it up to a configured minimum without touching
  NetBank Inventory or Account Treasury Positions.
- The isolated boundary is accepted only in configured synthetic
  environments and fails closed elsewhere.
- The version 2 report now exposes the system issuer, compatibility boundary,
  principal, instruction cost, Account debit, price components, recipient
  Account readiness, persisted execution policy, and observed provider
  attempt count.
- Scenario success now requires the provider-attempt count to remain zero;
  `provider_calls: false` is derived from persisted evidence instead of being
  a hard-coded assertion.

### 2026-07-30 — Slice 10 completed

- Persisted the generic `execution_only` post-redemption mode on onboarding
  Voucher instructions.
- Prepended an execution-aware gate to the shared post-redemption pipeline.
  It suppresses external payout only when that persisted mode is present;
  ordinary disbursement and Campaign authorization behavior are unchanged.
- This corrects the earlier lifecycle false positive in which Account
  provisioning succeeded but the legacy redeemed observer still attempted a
  NetBank transfer.
- Focused policy, driver, pipeline, and serialization verification passes.

### 2026-07-30 — Slice 9 completed

- Reproduced the host-only failure where lifecycle preparation funded the
  compatibility wallet but a pre-existing zero Treasury Client Funds position
  correctly blocked issuance.
- Added a dedicated, prepared onboarding-scenario issuer so claim-architecture
  acceptance never borrows the system user or a real operator Account.
- Kept the scenario on an explicit isolated compatibility-ledger boundary.
  It still estimates and charges the onboarding tariff, but it cannot create
  synthetic NetBank Inventory or Treasury Positions.
- Preserved normal Cockpit and Treasury funding safeguards without a bypass.
- Made lifecycle preparation top up synthetic wallets to a configured minimum
  instead of adding the full amount again on every run.
- Added regression coverage for a host with a zero system Treasury position,
  explicit scenario-issuer resolution, and restoration of the commercial
  runtime setting after issuance.

### 2026-07-30 — Slice 0 opened

- Established this Compass as the canonical workstream memory.
- Locked the explicit workflow key, driver key, claim requirements,
  authentication modes, OTP policy, pricing ownership, and package boundaries.
- Confirmed the implementation will extend the campaign claim-workflow seam
  instead of creating route-name or Vue field-guessing special cases.

### 2026-07-30 — Slice 1 completed

- Added `VoucherInstructionsData::$onboarding` with a safe default of `false`.
- Preserved the legacy clean wire shape by omitting the canonical field when
  it is false.
- Serialized explicit onboarding intent when true.
- Mapped only legacy `claim.onboarding.mode=required` to the canonical flag.
- Kept legacy `if_required` outside the new onboarding classification.
- Allowed an explicit canonical false value to override legacy compatibility
  metadata.
- Focused Voucher DTO, claim instruction, and instruction-domain tests pass:
  21 tests and 69 assertions.

### 2026-07-30 — Slice 2 completed

- Published Pay Code commercial catalog version 3 with the explicit
  `onboarding.enabled` item.
- Set the initial Account Onboarding tariff to ₱10.00; future price changes
  require another catalog version and do not rewrite accepted quotes.
- Added the matching `3neti/instruction` product projection with catalog
  reference and version metadata.
- Added onboarding selection to the x-change deterministic commercial quote.
- Missing onboarding catalog support now fails closed instead of silently
  producing a free instruction.
- Focused verification passes:
  - x-commerce: 6 tests and 25 assertions;
  - instruction product projection: 2 tests and 6 assertions;
  - x-change pricing: 8 tests and 33 assertions.

### 2026-07-30 — Slice 3 completed

- Added the generic claim authentication modes `none`,
  `authenticated_officer`, and `claimant_handoff`.
- Added one issuance dependency policy that compiles canonical onboarding
  intent into `onboarding_account_provisioning`.
- The policy derives Name, Email, Mobile, and effective OTP requirements before
  validation, pricing, preview, persistence, and execution.
- Local OTP bypass omits OTP; a specific-mobile restriction still forces it.
- Added the explicit `onboarding.account-provisioning.v1` descriptor with no
  editable amount or external payout destination.
- Form Flow now receives the descriptor in top-level metadata and every step,
  with required claimant fields marked from the descriptor rather than Vue
  guesses.
- Ordinary disbursement and campaign officer authorization remain unchanged.
- Focused verification passes:
  - normalization and commercial regression: 16 tests and 58 assertions;
  - claim workflow regression: 6 tests and 48 assertions.

### 2026-07-30 — Slice 4 completed

- Replaced the campaign-only session assumption with the generic
  `ClaimAuthenticationIntent` contract.
- Preserved `campaign_authorization` as the authenticated-officer intent and
  added the distinct `onboarding_claimant_handoff` intent.
- Both intents retain the canonical `/x/claim/{code}` intended URL.
- Claim controllers now enforce pre-claim login from the descriptor's
  `authenticated_officer` mode; `claimant_handoff` intentionally remains
  pre-auth until the execution driver provisions and authenticates the
  verified recipient.
- Login, mobile verification, and the protected-claim page consume generic
  intent title and description data instead of inferring behavior from route
  names.
- The former campaign-specific helper remains as a compatibility adapter.
- Focused verification passes:
  - package claim/authentication: 16 tests and 75 assertions;
  - root claim workflow regression: 6 tests and 48 assertions;
  - claim authentication frontend: 4 tests.

### 2026-07-30 — Slice 5 completed

- Registered `onboarding_account_provisioning` with the shared Voucher
  `ExecutionDriverRegistry`.
- Implemented the existing `3neti/onboarding`
  `ContactUserProvisionerContract` in x-change, keeping host User and Treasury
  knowledge outside the onboarding package.
- Account resolution is idempotent by normalized verified Mobile and rejects
  Email or Mobile ownership conflicts.
- New claimants receive one authenticatable User, an idempotent platform
  Account, and the configured Treasury Account positions.
- Account provisioning and Voucher redemption now share one database
  transaction. A failed redemption rolls back newly created Account state and
  does not consume the Voucher.
- Required OTP workflows fail before mutation without trusted verification
  evidence; locally disabled OTP workflows follow their persisted policy.
- Raw OTP and PIN material is stripped before Voucher redemption and is absent
  from execution results.
- Successful browser claims schedule claimant authentication after commit,
  regenerate the session identifier, and remove stale auth intent state.
- Focused execution and provisioning verification passes: 8 tests and
  39 assertions.

### 2026-07-30 — Slice 6 completed

- Added one explicit **Set Up Recipient Account** choice to the existing
  Quick Generate instruction builder.
- Enabling it emits only the canonical `onboarding: true` authoring flag.
- Full Name, Email, and Mobile become selected and locked dependencies.
- OTP is selected and locked when the effective onboarding policy requires it.
- A specific Mobile recipient continues to force OTP when the local sandbox
  override is disabled.
- The same payload feeds the live commercial estimate, claim preview,
  reusable template blueprint, and issuance request.
- The Cockpit page exposes only the effective OTP boolean; provider details,
  secrets, and authentication internals remain server-side.
- Focused page and frontend verification passes: 8 tests and 16 backend
  assertions.

### 2026-07-30 — Slice 7 completed

- Saved personal templates and Last Pay Code reuse retain the canonical
  `onboarding` flag because they consume the same Quick Generate payload.
- Campaign worksheets now author the same boolean instead of encoding new
  behavior through `claim.onboarding.mode`.
- Campaign onboarding locks Full Name, Email, Mobile, and the effective OTP
  safeguard for every beneficiary Pay Code.
- Frozen legacy Campaign blueprints retain their historical shape and remain
  readable; only legacy `required` maps to canonical onboarding.
- Beneficiary issuance now passes the frozen blueprint through the shared
  issuance normalizer before Voucher creation, producing the same execution
  driver and claim dependencies as single issuance.
- Focused Campaign persistence, compilation, and frontend verification passes.

### 2026-07-30 — Slice 8 completed

- Added the deterministic `onboarding_voucher` lifecycle scenario.
- The scenario issues and claims a canonical onboarding Pay Code, provisions
  or reuses the recipient User and Account positions, and reports the
  canonical `/x/claim/{code}` link without calling a payout provider.
- Lifecycle preparation now synchronizes the active immutable x-commerce
  catalog into wallet-backed instruction products after any configured legacy
  seeder. This keeps `onboarding.enabled` priced and allocatable even while an
  installed instruction-package release is being upgraded.
- Corrected authentication-evidence redaction at the execution boundary:
  raw OTP codes are removed, while the non-secret `verified` marker and
  verification timestamp survive for Voucher contract validation.
- The lifecycle proves the Voucher is consumed once, the recipient Mobile is
  verified, the platform Account exists, Treasury positions are provisioned,
  no provider call occurs, and no raw OTP is persisted.
- Browser acceptance on the rebuilt production bundle confirms:
  - **Set Up Recipient Account** is visible in Claim Requirements;
  - Full Name, Mobile, and Email are selected and locked;
  - sandbox OTP may remain optional for an unrestricted recipient;
  - entering a specific Mobile immediately selects and locks OTP;
  - the commercial estimate updates from ₱40.50 to ₱51.30 for local
    onboarding, then to ₱55.00 when the Mobile-specific OTP safeguard applies.
- Focused lifecycle, execution, normalization, pricing, workflow, and
  authentication verification passes: 32 tests and 166 assertions.
- Production asset build and Cockpit asset diagnostics pass. The known
  third-party Rolldown pure-annotation warnings remain non-blocking.
