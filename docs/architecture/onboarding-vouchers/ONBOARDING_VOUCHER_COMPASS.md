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
Current slice: Authoritative Identity OTP
Status: Slices 0–18 complete
Last updated: 2026-08-02

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
| 10 | Execution-Aware Payout Isolation | Completed |
| 11 | System Account Lifecycle Issuance | Completed |
| 12 | Neutral Lifecycle Rider | Completed |
| 13 | Neutral Public Claim Preview | Completed |
| 14 | Unified Rider Fallback Policy | Completed |
| 15 | Truthful Ledger and Browser Payee Acceptance | Completed |
| 16 | Treasury-Backed Onboarding Grant | Completed |
| 17 | Provider-Neutral OTP Challenge Gateway | Completed |
| 18 | Structured Identity Proof Execution Boundary | Completed |

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
- The OTP form step sends only after an explicit claimant action. Rendering a
  claim never sends an SMS.
- The canonical identity purpose is `onboarding.account`; payout authorization
  uses a separate driver and purpose.
- `XCHANGE_ONBOARDING_REQUIRE_OTP` defaults to `true`.
- The sandbox may set it to `false` for local testing.
- A specific-mobile restriction always forces OTP, even when the local
  onboarding override is disabled.
- Production diagnostics must fail closed when onboarding OTP is disabled.
- Production diagnostics also require `XCHANGE_IDENTITY_OTP_DRIVER=txtcmdr`,
  an HTTPS `TXTCMDR_API_URL`, and a scoped `TXTCMDR_API_TOKEN`.

### Authentication

- Campaign officer authorization and claimant onboarding use a generic claim
  authentication-intent contract with distinct modes.
- Campaign authorization remains `authenticated_officer`.
- Onboarding uses `claimant_handoff`.
- Successful manual authentication regenerates the session identifier.
- OTPs, PINs, and raw identity evidence never enter Voucher metadata, claim
  workflow descriptors, or journal payloads.
- The execution engine accepts only a server-collected proof containing the
  provider challenge reference, `onboarding.account` purpose, verified mobile,
  and a fresh verification time. A raw code or timestamp is insufficient.

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
| `3neti/form-handler-otp` | Provider-neutral challenge gateway, explicit send/resend UI, and structured proof transport |
| `3neti/txtcmdr` | Versioned OTP authority, queued SMS delivery, challenge ownership, and atomic verification |
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
9. A proof for another challenge, purpose, or mobile cannot provision an
   Account.
10. OTP delivery jobs contain only a challenge identifier and run on the
    dedicated `txtcmdr-otp` queue in the txtcmdr deployment.

## Slice Log

### 2026-08-02 — Slices 17–18 authoritative identity OTP completed

- Published txtcmdr `v1.1.0` with authenticated, owner-bound `/api/v1/otp`
  challenge endpoints, idempotent creation, replay-safe verification, and
  dedicated queued delivery.
- Added a provider-neutral gateway to the OTP Form Flow handler. The handler
  no longer sends while rendering and never returns a raw OTP to x-change.
- Separated claimant identity verification from the withdrawal/Paynamics OTP
  authorization contract.
- Persisted the provider challenge reference and purpose on the local
  onboarding challenge while retaining only a keyed hash of the mobile.
- Removed the local `000000` browser disclosure. Simulated OTP remains limited
  to rollback-only lifecycle runners and is never a deployment driver.
- Made the onboarding execution driver fail closed unless Form Flow supplies a
  fresh, mobile-bound `onboarding.account` proof. Provider references are
  stripped before ordinary Voucher redemption; only a one-way audit hash is
  retained in the execution result.

### 2026-08-02 — Slice 19 live identity OTP acceptance completed

- Deployed txtcmdr `v1.1.0` to Laravel Cloud and provisioned an x-change
  client token limited to `otp:request` and `otp:verify`.
- Added the dedicated managed `txtcmdr-otp` worker and proved a real challenge
  reaches the `ready` delivery state without using the default queue.
- Completed a live verification through x-change using txtcmdr's immutable
  provider proof. Mobile verification and Account provisioning committed
  atomically; Treasury and provider balances were untouched.
- Corrected proof freshness validation to compare the provider UTC timestamp
  with a bounded current-time window instead of an application-timezone
  database timestamp.
- Added provider-authoritative recovery for a proof verified on time when the
  local challenge expires before x-change can persist it. A provider-expired
  proof remains rejected and the local challenge remains expired.
- Focused mobile verification and Account provisioning verification passes:
  17 tests and 66 assertions.

### 2026-07-30 — Slice 16 core accounting completed

- Extended the guarded system Account Funding Pay Code issuer with an explicit
  onboarding intent; the normalizer still owns compilation into the
  `onboarding_account_provisioning` execution driver.
- Composed Account provisioning and the existing `account_funding` claim
  outcome inside the execution engine rather than adding an onboarding-only
  transfer path.
- Issuance moves exactly the grant principal from the system Account Funding
  Reserve to Pay Code Reserve. Successful claim moves that same principal to
  the newly provisioned Account's Client Funds.
- Provider Inventory is unchanged and no bank or EMI payout is attempted.
- Replays reuse the same issuance, claim, and Treasury operation. The generic
  claim recorder reuses the already-settled Account Funding claim instead of
  creating a duplicate claim row.
- Insufficient system reserve rolls back the issuance record, Voucher,
  reserve movement, and journal lifecycle.
- The earlier claimant identity remains untouched for audit continuity. The
  browser acceptance identity for this slice is Sofia Hurtado, Mobile ending
  `6237`, with a ₱15.00 grant.
- Added the persistent `treasury_onboarding_grant` lifecycle mode. It requires
  a stable run reference, defaults to Sofia and ₱15.00, and supports
  `--no-claim` so the canonical browser claim can be accepted separately.
- Its report exposes the exact system Account Funding Reserve, Pay Code
  Reserve, recipient Client Funds, provider Inventory, provider-attempt count,
  claim count, and append-only journal event sequence.
- Focused lifecycle verification proves issue replay, full claim, missing
  run-reference rejection, one claim row, unchanged Inventory, and zero
  provider attempts.
- In-app browser acceptance completed `FUND-A4HU` for Sofia Hurtado, Mobile
  ending `6237`:
  - the system Account Funding Reserve moved from ₱18.02 to ₱3.02 at issuance;
  - Pay Code Reserve moved from ₱0.00 to ₱15.00, then returned to ₱0.00;
  - Sofia's new Account received exactly ₱15.00 Client Funds;
  - provider Inventory remained ₱1,956.32;
  - one Account Funding claim and the exact
    `issued → outcome_selected → applied` journal sequence were persisted;
  - no provider reconciliation or external payout was created;
  - claimant handoff authenticated Sofia into Cockpit, where the global header
    displayed Client Funds of ₱15.00.
- Local OTP was disabled for this acceptance run, so Sofia's
  `mobile_verified_at` correctly remains null. Production retains the
  fail-closed OTP default.
- Durable lifecycle replay now reuses the original expiry timestamp and
  reports an already-claimed Pay Code with the existing recipient Account and
  positions instead of trying to issue or settle it again.

### 2026-07-30 — Slice 15 completed

- Raised the onboarding characterization principal from ₱1.00 to ₱50.00.
- Added explicit `--claim-mobile`, `--claim-name`, and `--claim-email`
  lifecycle overrides so operators no longer need temporary environment
  variables to identify the claimant.
- The version 2 report now distinguishes the actual instruction debit from
  the estimated principal-plus-instructions commitment. It no longer presents
  a cached wallet snapshot as an actual principal debit.
- The report exposes recipient Client Funds and Pay Code Reserve positions,
  provider payout, principal disposition, and the remaining product decision.
- Lifecycle claims no longer fabricate OTP verification evidence when the
  persisted onboarding policy disables OTP.
- In-app browser acceptance completed `TEST-VTPE`, issued by system Account
  user `#1`, for claimant Mobile ending `0722`:
  - the clean onboarding claim collected Mobile, Full Name, and Email;
  - the execution driver created user `#29`, a platform Account, and zeroed
    Client Funds and Pay Code Reserve positions;
  - claimant handoff authenticated the new Account into Cockpit;
  - no provider reconciliation or external payout was created;
  - local OTP was disabled, so `mobile_verified_at` correctly remained null.
- The ₱50 principal remains a characterization liability under the isolated
  lifecycle boundary. A live-money onboarding scenario must reserve real
  Treasury Client Funds and use the explicit live-provider confirmation gate;
  increasing the denomination alone must never bypass that boundary.

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

### 2026-07-31 — Slice 9 completed

- Newly provisioned onboarding claimants are now marked as requiring an
  initial PIN instead of being given a PIN they do not know.
- Authenticated claim completion hands the claimant to a dedicated
  **Create Your PIN** screen before any Cockpit or settings page is available.
- Initial setup asks only for the new PIN and confirmation. It deliberately
  does not ask for a current PIN.
- The requirement is persisted in Account onboarding metadata and cleared
  only after the new PIN is hashed and stored successfully.
- Existing settings security confirmation remains unchanged for later PIN
  changes by established users.
- Focused onboarding provisioning, execution, and PIN setup verification
  passes: 15 tests and 60 assertions.
- Commit: `f2f22dcc` (`feat: require onboarding claimants to create pin`).

### 2026-07-31 — Slice 10 completed

- Enabled Fortify's standard password-reset broker as the recovery authority,
  presented to mobile-first users as **Forgot PIN**.
- Login now links to the email-based recovery request when reset-password
  support is enabled.
- The emailed reset token opens a dedicated **Reset PIN** form that accepts a
  numeric PIN and confirmation.
- The reset action uses Laravel's broker token, hashes the new PIN, and also
  clears a pending first-time PIN requirement when recovery is used.
- Recovery does not disclose whether an email address belongs to an Account;
  Fortify retains its standard throttling, token expiry, and single-use reset
  semantics.
- Focused authentication and onboarding regression verification passes:
  20 tests and 69 assertions.
- Production asset compilation passes; known third-party Rolldown
  pure-annotation warnings remain non-blocking.
- Commit: `6b93082a` (`feat: add mobile-first pin recovery`).
