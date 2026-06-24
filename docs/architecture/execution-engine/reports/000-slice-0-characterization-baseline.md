# Slice 0 Report: Characterization Baseline

Date: 2026-06-24  
Status: Completed

## Repositories

- x-change: `/Users/rli/PhpstormProjects/x-change-sandbox/packages/x-change`
- voucher: `/Users/rli/PhpstormProjects/packages/voucher`
- Repositories were inspected, tested, and prepared for commits independently.

## Files Inspected

Voucher areas:

- `src/Actions/GenerateVouchers.php`, `src/Actions/RedeemVoucher.php`
- `src/Events/VouchersGenerated.php`, `src/Listeners/HandleGeneratedVouchers.php`
- `src/Observers/VoucherObserver.php`, `src/Handlers/HandleRedeemedVoucher.php`
- `config/voucher-pipeline.php` and all configured generation, mint-cash, and redemption steps
- `src/Services/MintCash.php`, `src/Services/RedemptionContractEngine.php`
- `src/Support/RedemptionEvidenceExtractor.php`, validators, service provider, and related tests

x-change areas:

- claim web/API routes, controllers, requests, compiler/submission actions, and tests
- `SubmitCompiledFormClaim`, `SubmitPayCodeClaim`, `RedeemPayCode`, `WithdrawPayCode`
- `DefaultClaimExecutionFactory`, redemption/withdrawal execution services and contracts
- `DefaultRedemptionProcessorService`, `WithdrawalPipeline`, all configured withdrawal steps
- payout execution, provider event listeners, payment callbacks, and reconciliation services/tests
- lifecycle scenario configuration/bootstrapper and turnkey scenario tests

## Architectural Observations

- Voucher currently owns generation, redemption mutation, validation, and the global lifecycle pipeline.
- x-change currently chooses claim workflow and owns withdrawal/provider/reconciliation orchestration.
- The existing x-change execution abstractions are not the planned voucher execution engine.
- `voucher-pipeline.php` is a global compatibility pipeline and was not modified.
- Settlement Envelope is currently checked through an x-change readiness seam; actual envelope execution is not present.

## Tests Added or Strengthened

Voucher:

- Strengthened `tests/Feature/Issuance/IssueVoucherHappyPathTest.php` with exact `post-generation` and `mint-cash` pipeline ordering.
- Added characterization that generation completes post-processing and attaches one minted cash entity.

x-change baseline maintenance discovered before Slice 0:

- Updated stale validated-payload and compiled-claim payload expectations.
- Made unsupported-result testing compatible with named-slice enrichment.
- Loaded onboarding migrations in the package Testbench database and used the fake user's mobile-channel API.
- Propagated explicit lifecycle scenario provider and fixed the turnkey smoke scenario to use deterministic `manual` funding after readiness scenarios.

No execution-engine production classes, public APIs, voucher behavior, money movement, claim UX, or validation semantics were changed.

## Coverage Observations

Existing tests already protect successful/rejected redemption, exact post-redemption order, presence and semantic rules, claim submit through redeem and withdraw, open-slice branch conditions, successful payout, provider failure/pending state, reconciliation, and legacy vouchers. The material uncovered gap was generation pipeline order and processed/cash side effects.

## Commands and Outcomes

```text
voucher: vendor/bin/pest
baseline before changes: 312 passed, 28 skipped (867 assertions)

voucher focused:
vendor/bin/pest --compact tests/Feature/Issuance/IssueVoucherHappyPathTest.php tests/Unit/Actions/RedeemVoucherTest.php
17 passed (35 assertions)

voucher final full suite
314 passed, 28 skipped (871 assertions)

x-change focused execution subset
32 passed (71 assertions)

x-change lifecycle group/bootstrapper regression
18 passed (94 assertions)

x-change focused changed/execution tests
43 passed (269 assertions)

x-change Feature suite
388 passed, 5 skipped (2351 assertions)

x-change Unit claim-support subset
69 passed, 5 failed (285 assertions)
the five failures pre-date Slice 0 and are described below

sandbox lifecycle command
php artisan xchange:lifecycle:run turnkey_basic_cash_mobile --no-claim --json --no-interaction
exit 0; voucher generated successfully
```

Final full-suite outcomes are recorded below after the final verification run.

## Discrepancies and Resolutions

1. Planning material could be read as if no x-change execution abstractions existed. Actual source contains claim execution factory/contracts/services and a withdrawal pipeline. Canonical docs explicitly classify these as current product workflow seams.
2. The turnkey group implicitly selected Paynamics after an earlier readiness scenario created a ready link. The scenario now carries `provider: manual`; lifecycle input propagation is covered by a test.
3. The onboarding package was installed but its migration was absent from x-change Testbench. The test harness now loads it.
4. `CompiledClaimApprovalMetadataTest` and `CompiledClaimResultSessionTest` disagree with current production approval-metadata ordering, and the former expects scalar/null coercion that `CompiledClaimApprovalMetadata::normalize()` does not currently perform. This produces five unrelated unit failures. Correcting production would change claim metadata behavior, so Slice 0 leaves it untouched and records it for separate approval.

None of these findings contradict the target ownership rule that the future execution runtime belongs to voucher.

## Risks and Recommendations

- Extract the two concrete voucher dependencies first in Slice 1; do not combine this with engine work.
- Treat voucher lifecycle and x-change withdrawal pipelines as separate compatibility surfaces.
- Add architecture source-scan tests only when contracts exist, otherwise they would assert a future architecture prematurely.
- Keep provider-specific implementations outside voucher.
- Continue using explicit provider settings in deterministic lifecycle scenarios.

## Next Slice

Recommended: Slice 1 — Contract Extraction. Authorization has not been granted. Stop after Slice 0 and request approval.

## Repository-specific Commits

- voucher `6b7d2a0` — `test(execution-engine): characterize voucher runtime for slice 0`
- x-change `136f12f` — `test(x-change): restore lifecycle baseline`
- x-change `a2599f7` — `docs(execution-engine): establish slice 0 architecture baseline`
