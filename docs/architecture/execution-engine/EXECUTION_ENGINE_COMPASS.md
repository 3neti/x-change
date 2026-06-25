# Execution Engine Compass

## Mission

Evolve voucher redemption into a programmable, voucher-owned execution runtime while preserving all existing behavior and keeping x-change as the settlement operating-system/product orchestration layer.

## Current Position

Current slice: Slice 4 — Default Driver Extraction  
Status: Completed  
Last updated: 2026-06-25

| Slice | Name | Status |
|---|---|---|
| 0 | Characterization Baseline | Completed |
| 1 | Contract Extraction | Completed |
| 2 | Execution Instruction Introduction | Completed |
| 3 | Execution Engine Introduction | Completed |
| 4 | Default Driver Extraction | Completed |
| 5 | Driver Registry | Pending |
| 6 | Architecture Stabilization | Pending |
| 7 | Settlement Envelope Driver | Pending |
| 8 | Stored Value Driver | Pending |
| 9 | Driver-Composed Runtime | Pending / optional |

## Completed Work

- Commits: voucher `6b7d2a0`; x-change baseline `136f12f`; canonical documentation `a2599f7`.
- Created the five canonical architecture documents and Slice 0 report.
- Inspected generation, redemption, observers/handlers, all voucher lifecycle pipelines, validation, claim submission, branch selection, withdrawal, provider events, callbacks, and reconciliation.
- Added voucher issuance characterization for exact post-generation/mint-cash order and resulting processed/cash state.
- Restored the x-change baseline by correcting stale test expectations, loading onboarding migrations in Testbench, and isolating the turnkey manual-provider scenario.
- Confirmed the actual `turnkey_basic_cash_mobile` sandbox scenario succeeds with bank-side credits; no system/user wallet top-up was required for this path.
- Repaired approval metadata normalization and x-change package-local Testbench/Pest bootstrap.
- Added x-change package `phpunit.xml` with `memory_limit=1G`; `vendor/bin/pest` now uses the package-local default memory limit.
- Committed latest sandbox cleanup: `6b779bc` scaffold instructions, `d4ce09f` x-ray config and generated vendor ignore rule, `59b98f2` x-change package baseline runner.
- Verified x-change package full suite with default `vendor/bin/pest`: 968 passed, 5 skipped.
- Completed Slice 1 contract extraction in voucher: added `GeneratesVouchers` and `RedeemsVouchers` contracts, bound them to the existing `GenerateVouchers` and `RedeemVoucher` actions, and protected the bindings with package-local tests.
- Completed Slice 1 x-change adaptation: `PayCodeIssuanceService` and `DefaultRedemptionProcessorService` now consume voucher runtime contracts instead of importing concrete voucher actions.
- Added an x-change architecture guard that resolves the voucher runtime contracts and blocks production imports of the concrete voucher generation/redemption actions.
- Slice 1 commits: voucher `25e7b75` (`execution-engine: extract voucher runtime contracts`); x-change `55374ee` (`execution-engine: consume voucher runtime contracts`).
- Completed Slice 2 execution instruction introduction in voucher: added `ExecutionInstructionData`, optional execution support in `VoucherInstructionsData`, and an effective implicit default instruction with `driver: default`.
- Preserved legacy voucher metadata shape: vouchers with no explicit execution block still omit `metadata.instructions.execution`.
- Added tests for default instruction creation, explicit instruction hydration, default driver behavior, legacy instruction hydration, explicit instruction serialization, legacy issuance metadata preservation, and DTO autoloading.
- Slice 2 commit: voucher `477313b` (`execution-engine: introduce execution instruction data`).
- Completed Slice 3 execution engine introduction in voucher: added `ExecutionContextData`, `ExecutionResultData`, and a compatibility `ExecutionEngine`.
- The compatibility engine resolves the current execution instruction driver key, delegates actual redemption to the existing `RedeemsVouchers` contract, and returns structured success/failure result data.
- Preserved existing runtime behavior: the engine is not wired into legacy redemption, does not alter voucher metadata, and does not introduce driver contracts, driver classes, a registry, public API changes, claim UX changes, or money-movement changes.
- Added tests for context creation from vouchers, driver-key resolution, compatibility execution success/failure, metadata reporting, autoload coverage, and container resolution.
- Slice 3 commit: voucher `3dc846f` (`execution-engine: introduce compatibility execution engine`).
- Completed Slice 4 default driver extraction in voucher: added `ExecutionDriverContract` and `DefaultExecutionDriver`.
- Moved the existing voucher redemption behavior behind the default execution driver while preserving the public `RedeemVoucher` action and `RedeemsVouchers` contract.
- `RedeemVoucher` now routes through `ExecutionEngine`, and `ExecutionEngine` delegates to the bound default execution driver.
- Preserved existing runtime behavior: no registry, no alternate driver resolution, no settlement-envelope driver, no stored-value driver, no public API change, no claim UX change, and no money-movement semantic change.
- Added tests for default driver binding, no-instruction/default-instruction behavior, engine-to-driver delegation, `RedeemVoucher::run()` routing through the engine/default driver, autoload coverage, and container resolution.
- Slice 4 commit: voucher `2491132` (`execution-engine: extract default execution driver`).

## Discoveries

- x-change has existing execution-named workflow contracts, but the target voucher-owned engine, instruction, drivers, and registry do not exist.
- x-change directly imports concrete voucher `GenerateVouchers` and `RedeemVoucher` at exactly two production seams.
- Voucher's post-redemption pipeline validates then disburses; handled provider failures preserve redemption and become pending reconciliation.
- x-change withdrawal is a separate, configurable, traced pipeline with intent-first reconciliation.
- Provider readiness state can influence lifecycle provider selection; deterministic scenarios must carry their provider explicitly.
- Contract extraction was possible without introducing execution instructions, an execution engine, execution drivers, a driver registry, public API changes, voucher pipeline changes, or money-movement behavior changes.
- The default execution instruction is intentionally implicit for legacy vouchers; it is available via voucher instruction data but is not persisted into legacy clean instruction payloads unless explicitly supplied.
- Explicit execution blocks can now be serialized into voucher instruction metadata, but no runtime behavior consumes them yet.
- Slice 3 introduces a compatibility execution engine surface only. It resolves an instruction driver key, but it does not resolve or execute driver objects.
- `ExecutionResultData` now provides a structured return shape for execution outcomes, but those outcomes are not persisted or journaled yet.
- Slice 4 moves the current redemption behavior into `DefaultExecutionDriver`. This makes the runtime path `RedeemVoucher -> ExecutionEngine -> DefaultExecutionDriver -> existing behavior`.
- Driver resolution is still not registry-based. The engine has only the default driver dependency until Slice 5.

## Risks

- Global post-redemption behavior couples redemption activation to payout consequence.
- Two different pipeline concepts can be confused during extraction.
- Contract extraction spans separate repositories and requires ordered, independent commits.
- Settlement readiness/pending behavior could be mistaken for implemented envelope execution.
- Full suites depend on linked local packages and normal Testbench filesystem permissions.
- Voucher and x-change have separate package test environments; run each package from its own root with its own `vendor/bin/pest`, `tests/Pest.php`, and `tests/TestCase.php`.
- x-change now depends on the newly extracted voucher contract names being available from the linked/local voucher package.
- Later slices must not assume `ExecutionInstructionData` implies engine execution; it is currently metadata only.
- Later slices must not treat the Slice 3 compatibility engine as the final driver-composed runtime. The current executor still delegates to the existing redemption contract.
- Slice 4 intentionally extracts only the default driver. Adding unknown-driver handling or extension APIs before the registry slice would spread resolution policy into the wrong layer.

## Architectural Decisions

- Voucher owns future execution semantics.
- x-change owns claim/product orchestration and provider integrations.
- Provider implementations stay outside the voucher engine behind contracts.
- Settlement Envelope is a readiness/authorization participant, not the engine.
- Existing `voucher-pipeline.php` is the Slice 0 compatibility baseline and remains unchanged.
- Planning DTO/class snippets are illustrative until introduced test-first in an authorized slice.
- Slice 1 contracts are compatibility seams around existing actions only; they are not the future execution engine, instruction model, driver contract, or registry.
- Slice 2 introduces only the instruction DTO/metadata boundary. It does not introduce `ExecutionEngine`, `ExecutionContextData`, `ExecutionResultData`, driver contracts, driver implementations, or a registry.
- Slice 3 introduces the first engine surface in voucher, but deliberately keeps execution behavior behind the existing `RedeemsVouchers` contract.
- Slice 4 makes the default driver the compatibility owner for current redemption behavior. The public `RedeemsVouchers` contract remains a stable entrypoint and now routes through the engine/default-driver path.
- Driver registry, settlement-envelope execution, stored-value execution, and driver-composed runtime remain future slices.
- x-change's current redemption workflow contract remains an adapter during the Execution Engine migration. No x-change call-site replacement is authorized during Slices 0–6.
- The execution path during early migration is `Claim Submit -> x-change Workflow Contract -> Voucher Redemption Contract -> Execution Engine -> Execution Driver`.
- Voucher owns `ExecutionDriverRegistry`, `ExecutionDriverContract`, driver resolution rules, default driver resolution, and unknown-driver failure behavior.
- x-change and future packages may contribute drivers through voucher-owned registry extension points.
- The precise driver registration API remains an implementation detail, but registry ownership is settled.
- Every execution must have a durable `execution_id` as the primary correlation mechanism.
- `ExecutionResultData` should return `execution_id`, `status`, `events`, and `metadata`; persistence and journaling remain deferred.
- The Execution Engine must be journal-ready but not journal-dependent. Do not scaffold x-journal, journal tables, journal repositories, or journal storage in the execution-engine migration.
- Explicit execution instructions require schema versioning before production use. Target shape: `execution.schema: voucher.execution.v1` and `execution.driver: default`.
- Legacy vouchers without execution instructions continue using implicit compatibility behavior.
- Schema/version hardening should be finalized before non-default drivers become production-capable, preferably across Slice 5 registry work, Slice 6 schema hardening, and before Slice 7 first non-default driver.
- Slice 5 should introduce a singleton `ExecutionDriverRegistry` bound in the voucher service provider.
- The registry resolves drivers but does not execute drivers.
- Unknown execution drivers must fail closed with `UnknownExecutionDriverException` before any execution side effect occurs.
- Driver resolution rules are: no execution block resolves default driver; explicit default resolves default driver; explicit known driver executes that driver; explicit unknown driver fails closed.

## Test Coverage Status

- Characterization: strong across the identified danger zone.
- Contract extraction: completed for voucher generation/redemption runtime seams.
- Execution instruction: completed as optional voucher metadata with an implicit legacy default.
- Execution engine introduction: completed as a compatibility surface with context/result DTOs and no runtime wiring change.
- Default driver extraction: completed with `ExecutionDriverContract`, `DefaultExecutionDriver`, engine delegation, and `RedeemVoucher` routing through the engine/default driver.
- Architecture invariants: x-change now has an executable guard preventing production imports of concrete voucher generation/redemption actions.
- Feature/regression: current voucher and x-change suites cover issuance, redemption, claim, withdrawal, provider failure, and reconciliation.
- Verification: voucher full suite is green as of 2026-06-25 with 334 passed and 28 skipped; x-change package full suite is green as of 2026-06-25 with 970 passed and 5 skipped.

## Next Recommended Slice

Slice 5 — Driver Registry, only after explicit human approval. Start in voucher with failing tests for registry registration, default-driver resolution by key, clear unknown-driver failure, and package-consumer extension seams. Keep only the default driver registered initially.

## Open Questions

- Exact package-level driver registration API shape for Slice 5.
- Exact `execution_id` generation mechanism and where it is introduced.
- Exact schema hardening scope for Slice 6.
- Exact `UnknownExecutionDriverException` namespace and error payload.
