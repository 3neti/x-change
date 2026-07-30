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
Current slice: Slice 6 — Issuance Dependency UX
Status: Slice 5 complete; Slice 6 in progress
Last updated: 2026-07-30

| Slice | Name | Status |
|---|---|---|
| 0 | Compass and Boundary Lock | Completed |
| 1 | Minimal Voucher Instruction Contract | Completed |
| 2 | Commercial Catalog and Instruction Product | Completed |
| 3 | Explicit Claim Workflow Descriptor | Completed |
| 4 | Generic Claim Authentication Handoff | Completed |
| 5 | Account-Provisioning Execution Driver | Completed |
| 6 | Issuance Dependency UX | In progress |
| 7 | Template and Campaign Propagation | Pending |
| 8 | Lifecycle and Browser Acceptance | Pending |

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
