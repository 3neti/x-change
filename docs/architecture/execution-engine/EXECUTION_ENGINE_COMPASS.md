# Execution Engine Compass

## Mission

Evolve voucher redemption into a programmable, voucher-owned execution runtime while preserving all existing behavior and keeping x-change as the settlement operating-system/product orchestration layer.

## Current Position

Current slice: Slice 7 — Settlement Envelope Driver
Status: Completed  
Last updated: 2026-06-29

| Slice | Name | Status |
|---|---|---|
| 0 | Characterization Baseline | Completed |
| 1 | Contract Extraction | Completed |
| 2 | Execution Instruction Introduction | Completed |
| 3 | Execution Engine Introduction | Completed |
| 4 | Default Driver Extraction | Completed |
| 5 | Driver Registry | Completed |
| 6 | Architecture Stabilization | Completed |
| 7 | Settlement Envelope Driver | Completed |
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
- Completed Slice 5 driver registry in voucher: added singleton `ExecutionDriverRegistry` and `UnknownExecutionDriverException`.
- `ExecutionEngine` now resolves the execution instruction driver key through the registry before executing a driver.
- The voucher service provider registers only the `default` driver initially.
- Added package-consumer extension seam through `ExecutionDriverRegistry::register()`, with tests proving extension drivers can be registered and executed without changing engine conditionals.
- Added fail-closed unknown-driver tests proving explicit unknown drivers throw before any driver side effect executes.
- Added support coverage for registry autoloading and singleton container resolution.
- Slice 5 commit: voucher `adf3ae7` (`execution-engine: add execution driver registry`).
- Completed Slice 6 architecture stabilization in voucher: hardened execution instructions with canonical schema versioning and execution results with durable `execution_id` correlation.
- Added architecture guards proving public voucher redemption still passes through `ExecutionEngine`, driver resolution remains registry-only, only the default driver is package-registered, and later-slice drivers have not been scaffolded.
- Added result-shape coverage proving succeeded and failed execution results expose `execution_id`, `status`, `events`, and `metadata` consistently without adding persistence or journaling.
- Preserved legacy compatibility: implicit default execution instructions are still not persisted into legacy voucher instruction payloads, while explicit execution instructions now serialize `schema: voucher.execution.v1`.
- Slice 6 commit: voucher `04ecddd` (`execution-engine: stabilize architecture contracts`).
- Completed Slice 7 settlement-envelope driver scaffold in voucher: added `SettlementEnvelopeExecutionDriver`, `SettlementEnvelopeExecutionGateway`, `NullSettlementEnvelopeExecutionGateway`, and `SettlementEnvelopeNotReadyException`.
- Registered `settlement_envelope` as the first non-default built-in execution driver while preserving `default` driver behavior and registry-based resolution.
- Added driver tests for authority-voucher execution, configured envelope loading, readiness verification before side effects, lock-before-child-generation ordering, child voucher generation, optional child auto-redemption, and claim-fallback voucher generation on failed child execution.
- Kept settlement-envelope readiness/gating behind a gateway seam. Voucher orchestrates execution semantics; settlement-envelope/x-change can provide concrete readiness and envelope access by binding the gateway contract.
- Preserved later-slice boundary: `StoredValueExecutionDriver` remains absent.
- Slice 7 commit: `4478639 execution-engine: add settlement envelope driver`.

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
- Slice 5 makes driver resolution registry-based. The engine no longer directly depends on the default driver.
- The package-level extension API is `ExecutionDriverRegistry::register(string $key, ExecutionDriverContract|string|Closure $driver)`.
- The unknown-driver exception namespace is `LBHurtado\Voucher\Exceptions\UnknownExecutionDriverException`.
- Slice 6 introduces the canonical instruction schema field as `ExecutionInstructionData::SCHEMA = voucher.execution.v1`.
- `ExecutionResultData` now assigns a durable UUID `execution_id` to every succeeded and failed result unless an explicit execution id is supplied by the caller.
- Slice 7 resolves the package boundary by introducing a voucher-owned `SettlementEnvelopeExecutionGateway` seam instead of putting settlement-envelope readiness logic directly into the execution driver.
- `SettlementEnvelopeExecutionDriver` uses the same `execution_id` across the returned result and child auto-redemption metadata.

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
- Slice 5 intentionally registers only the default driver. Non-default registration by x-change or future packages must remain explicit and additive.
- Execution result persistence remains deferred. `execution_id` is now present for correlation, but results are not persisted or journaled by the execution engine.
- Schema hardening is limited to the instruction-level `schema` field. No non-default driver schema contract has been introduced yet.
- The default settlement-envelope gateway is intentionally non-operational and fails readiness until a concrete gateway is bound by a consuming package.
- The Slice 7 gateway currently defines the envelope-child-voucher translation seam; a concrete implementation must avoid duplicating x-change claim UX or provider behavior.

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
- Slice 5 introduced the singleton registry and locked the extension API to key-based registration.
- The default package registration list contains only `default` until later slices explicitly add non-default drivers.
- Slice 6 sets the current canonical execution instruction schema to `voucher.execution.v1`.
- Slice 6 implements result correlation in `ExecutionResultData` through an `execution_id` UUID generated at result construction time.
- Slice 6 confirms execution persistence and x-journal integration remain deferred; the engine is correlation-ready and journal-ready, not journal-dependent.
- Slice 6 keeps the first non-default driver deferred. `SettlementEnvelopeExecutionDriver` and `StoredValueExecutionDriver` are intentionally absent.
- Slice 7 introduces `SettlementEnvelopeExecutionDriver` as the first non-default driver owned by voucher.
- Slice 7 keeps settlement-envelope policy/readiness outside voucher execution semantics through `SettlementEnvelopeExecutionGateway`.
- Slice 7 registers built-in drivers explicitly as `default` and `settlement_envelope`.
- Slice 7 does not introduce stored-value behavior, direct provider behavior, claim UX behavior, x-journal persistence, or a concrete x-change gateway binding.

## Test Coverage Status

- Characterization: strong across the identified danger zone.
- Contract extraction: completed for voucher generation/redemption runtime seams.
- Execution instruction: completed as optional voucher metadata with an implicit legacy default.
- Execution engine introduction: completed as a compatibility surface with context/result DTOs and no runtime wiring change.
- Default driver extraction: completed with `ExecutionDriverContract`, `DefaultExecutionDriver`, engine delegation, and `RedeemVoucher` routing through the engine/default driver.
- Driver registry: completed with singleton registry resolution, package extension registration, fail-closed unknown-driver behavior, and no engine if/else driver selection.
- Architecture stabilization: completed with instruction schema tests, execution result `execution_id` tests, registry-only resolution guards, default-only package registration guard, and no later-driver scaffold guard.
- Settlement-envelope driver: completed with gateway-seam tests for load, readiness, lock, child generation, auto-redemption, fallback claim-voucher generation, registry resolution, and stored-value absence.
- Architecture invariants: x-change now has an executable guard preventing production imports of concrete voucher generation/redemption actions.
- Feature/regression: current voucher and x-change suites cover issuance, redemption, claim, withdrawal, provider failure, and reconciliation.
- Verification: voucher full suite is green as of 2026-06-29 with 360 passed and 28 skipped; x-change package full suite is green with 970 passed and 5 skipped.

## Next Recommended Slice

Slice 8 — Stored Value Driver, only after explicit human approval. Add stored-value ownership/spend semantics as driver-specific behavior without changing default vouchers or settlement-envelope execution.

## Open Questions

- Concrete settlement-envelope gateway binding location: x-change service provider, settlement-envelope package provider, or a dedicated integration package.
- Exact instruction metadata shape for production settlement-envelope authority vouchers beyond the current `metadata.envelope_reference`, `metadata.auto_redeem_children`, and `metadata.fallback_to_claim` scaffold.
- Whether execution results should be persisted by voucher directly or only consumed by the future x-journal layer remains deferred.
