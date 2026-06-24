# Execution Engine: Test Strategy

Status: Canonical  
Last updated: 2026-06-24

## Method

Use Architectural TDD. Tests must protect both observable behavior and dependency direction. Assertions precede production classes in every later slice.

## Layers

| Layer | Purpose |
|---|---|
| Characterization | Freeze behavior that later extraction must preserve |
| Contract | Prove stable interfaces and container bindings |
| Unit | Define DTO, registry, driver, context, and result behavior |
| Feature | Protect issuance, claim, redemption, withdrawal, and payout flows |
| Architecture invariant | Prevent ownership and dependency drift |
| Regression | Preserve every defect discovered during migration |

## Slice 0 Coverage Matrix

| Danger zone | Current protection |
|---|---|
| Voucher generation and side effects | `IssueVoucherHappyPathTest`; exact post-generation/mint-cash order, processed flag, instruction metadata, cash attachment |
| Redemption and rejection | `RedeemVoucherTest` plus feature redemption tests |
| Post-redemption pipeline | Exact configured order and successful/pending outcomes |
| Presence validation | `ValidateRedemptionContractTest`, `RequiredInputFieldsValidatorTest` and redemption feature tests |
| Semantic validation | OTP, signature, selfie, location, time, and face-match validator tests |
| Claim submit | `SubmitCompiledFormClaimTest`, `SubmitPayCodeClaimTest`, API/web feature tests |
| Redeem/withdraw branch | `ClaimExecutionFactoryTest`, redeem/withdraw action tests |
| Withdrawal orchestration | execution, processor, pipeline, conditional/trace, and step tests |
| Payout success/failure/pending | voucher disbursement tests and x-change reconciliation/executor tests |
| Legacy behavior | legacy redeemable branch test and vouchers without any future execution instruction |

## Commands

Discover commands from each repository's `composer.json`. Current package commands are:

```bash
# voucher
vendor/bin/pest

# x-change
vendor/bin/pest

# focused examples
vendor/bin/pest --compact tests/Feature/Issuance/IssueVoucherHappyPathTest.php tests/Unit/Actions/RedeemVoucherTest.php
vendor/bin/pest --compact tests/Unit/Services/ClaimExecutionFactoryTest.php tests/Unit/Actions/Claim/SubmitCompiledFormClaimTest.php
```

Run focused tests first, then each relevant full suite. Tests in the external voucher repository require normal write access to Testbench logs and Pest's result cache.

## Later Slice Gates

- Slice 1: contracts resolve to existing implementations; x-change no longer imports concrete generation/redemption actions.
- Slice 2: absent execution instruction remains behaviorally identical and serialization is stable.
- Slices 3–4: all legacy paths pass through the engine/default driver with identical results and side effects.
- Slice 5: registry extension and unknown-driver failure are explicit.
- Slice 6: source-scan and behavior invariants are green.
- New-driver slices: the full default compatibility suite runs alongside driver-specific tests.

## Failure Policy

Classify every failure as a behavior regression, stale test assumption, environment limitation, or unrelated pre-existing defect. Do not weaken assertions to hide a behavior change. Do not stack slices on a red baseline.
