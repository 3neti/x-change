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
Current slice: Slice 0 — Compass and Boundary Lock
Status: In progress
Last updated: 2026-07-30

| Slice | Name | Status |
|---|---|---|
| 0 | Compass and Boundary Lock | In progress |
| 1 | Minimal Voucher Instruction Contract | Pending |
| 2 | Commercial Catalog and Instruction Product | Pending |
| 3 | Explicit Claim Workflow Descriptor | Pending |
| 4 | Generic Claim Authentication Handoff | Pending |
| 5 | Account-Provisioning Execution Driver | Pending |
| 6 | Issuance Dependency UX | Pending |
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

