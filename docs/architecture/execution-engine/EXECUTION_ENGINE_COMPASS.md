# Execution Engine Compass

## Mission

Evolve voucher redemption into a programmable, voucher-owned execution runtime while preserving all existing behavior and keeping x-change as the settlement operating-system/product orchestration layer.

## Current Position

Current slice: Slice 9 — Driver-Composed Runtime
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
| 8 | Stored Value Driver | Completed |
| 9 | Driver-Composed Runtime | Completed / optional |

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
- Completed Slice 8 stored-value driver scaffold in voucher: added `StoredValueExecutionDriver`, `StoredValueExecutionGateway`, `NullStoredValueExecutionGateway`, `StoredValueSpendRejectedException`, and `StoredValueSpendRequiresOtpException`.
- Registered `stored_value` as the second non-default built-in execution driver while preserving `default` and `settlement_envelope` driver behavior and registry-based resolution.
- Added stored-value tests for ownership activation, no cash disbursement on claim, slice spending, over-balance rejection, OTP threshold enforcement, replenishable vouchers, max-balance replenishment rejection, registry resolution, and execution through the engine.
- Kept stored-value ledger/wallet mutation behind a gateway seam. Voucher owns execution semantics; wallet/cash/x-change or a future integration package can provide concrete ledger behavior by binding the gateway contract.
- Preserved package boundary: no new voucher species, no direct wallet/provider implementation, no x-journal persistence, and no changes to default voucher disbursement behavior.
- Slice 8 commit: `9dbc7be execution-engine: add stored value driver`.
- Completed optional Slice 9 driver-composed runtime scaffold in voucher: added `ExecutionPipelineStepContract`, `ExecutionPipelineStateData`, `ExecutionPipelineStepRegistry`, `ExecutionPipelineRuntime`, and `UnknownExecutionPipelineStepException`.
- Added a package-level singleton registry/runtime for named execution pipeline blocks while keeping driver resolution in `ExecutionDriverRegistry`.
- Added driver-composed runtime tests for step registration, unknown-step failure, ordered step execution, class-string and closure resolution, short-circuiting finalized results, and a fake driver assembling pipeline blocks from instruction metadata.
- Preserved existing drivers: `default`, `settlement_envelope`, and `stored_value` are not rewritten to use the pipeline runtime in this slice.
- Added an architecture guard proving the central `ExecutionEngine` remains driver-only and does not compose pipeline steps directly.
- Slice 9 commit: `d7abbe6 execution-engine: add driver composed runtime`.

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
- Slice 8 confirms stored-value can be modeled as execution-driver behavior without introducing `StoredValueVoucher` as a separate voucher species.
- `StoredValueExecutionDriver` uses `context.meta.operation` for driver operations: `activate` by default, plus `spend` and `replenish`.
- Stored-value spend/replenishment returns the same durable `execution_id` through the result and gateway call.
- Slice 9 introduces a generic opt-in pipeline runtime. Drivers may assemble modular execution steps, but the central engine still only resolves and executes drivers.
- Pipeline steps can be registered as instances, class strings, or closures through `ExecutionPipelineStepRegistry`.
- Pipeline execution short-circuits when a step finalizes `ExecutionPipelineStateData::$result`.
- 2026-07-15 integration discovery: explicit execution schema rejection now happens at `ExecutionInstructionData` construction; only `voucher.execution.v1` is currently accepted.
- 2026-07-15 integration discovery: voucher drivers accept canonical nested metadata under `execution.metadata.settlement_envelope` and `execution.metadata.stored_value`, with legacy flat metadata fallback retained.
- 2026-07-15 integration discovery: x-change now binds concrete gateway adapters for `SettlementEnvelopeExecutionGateway` and `StoredValueExecutionGateway`; these prove the integration seam but are not yet production provider/ledger implementations.
- 2026-07-15 lifecycle verification: `execution_settlement_envelope_contract_demo` and `execution_stored_value_contract_demo` issue through x-change and execute through voucher `ExecutionEngine`, returning correlated execution results in JSON.

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
- The default stored-value gateway is intentionally minimal and does not perform real ledger mutation. Concrete ledger behavior must be bound by a consuming package before production stored-value spending.
- Stored-value operation metadata is currently scaffold-level and may need schema hardening before production use.
- Concrete stored-value gateway implementation must avoid duplicating wallet/cash ledger policy and must not bypass existing financial controls.
- Slice 9 is infrastructure only. Existing drivers are not yet decomposed into pipeline steps, so there is no production driver using modular blocks by default.
- Pipeline step naming/versioning needs the same production hardening as driver instruction metadata before issued vouchers depend on explicit step lists.
- Pipeline steps can encode side effects; concrete step registration must maintain fail-closed behavior before money movement or voucher mutation.
- Slice-level execution semantics are not yet typed in voucher. Current x-change/Cockpit named-slice metadata is only issuance/presentation guidance. If future execution drivers directly execute named, scheduled, condition-gated, open-spend, stored-value-like, or per-slice-balance rows, voucher should introduce a typed slice instruction contract before that behavior is production-capable.

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
- Slice 8 introduces `StoredValueExecutionDriver` as the second non-default driver owned by voucher.
- Slice 8 keeps stored-value wallet/ledger mutation outside voucher execution semantics through `StoredValueExecutionGateway`.
- Slice 8 registers built-in drivers explicitly as `default`, `settlement_envelope`, and `stored_value`.
- Slice 8 treats stored-value as execution behavior, not a new voucher model/species.
- Slice 8 does not introduce direct provider behavior, claim UX behavior, x-journal persistence, or a concrete wallet/cash/x-change gateway binding.
- Slice 9 introduces modular execution pipelines as opt-in driver infrastructure, not a replacement for `ExecutionEngine`.
- Slice 9 keeps the execution path as `ExecutionEngine -> ExecutionDriverRegistry -> ExecutionDriverContract`; drivers may then use `ExecutionPipelineRuntime` internally.
- Slice 9 does not migrate existing drivers into pipeline steps and does not introduce provider, wallet, x-journal, claim UX, or persistence behavior.
- Future executable slice policy belongs in voucher, not x-change or cash, when slices control execution consequences. Cash/provider packages should supply rail constraints, minimum disbursement policy, and money mechanics behind gateways; x-change should supply templates, Cockpit UX, campaign context, and beneficiary presentation.
- Candidate future voucher-owned contracts for that work include `VoucherSliceInstructionData` or `ExecutionSliceInstructionData`, `SliceExecutionPolicyData`, and `SliceExecutionResultData`. These remain future proposals until introduced test-first in an authorized voucher execution slice.
- The Quick Generate settlement/execution/metadata architecture note in `../../ui-cockpit/quick-generate-settlement-execution-metadata.md` records how x-change currently prepares optional execution instructions for voucher without becoming the execution runtime.
- x-change gateway binding strategy is now concrete for contract-demo purposes: x-change owns adapter bindings in `XChangeServiceProvider`; voucher continues to own engine, driver contracts, registry, and execution result shape.
- Canonical production metadata baselines are nested and driver-specific: `execution.metadata.settlement_envelope` and `execution.metadata.stored_value`.
- Executable slice instructions remain deferred. Current named slices stay x-change/Cockpit metadata until a future voucher execution slice introduces typed voucher-owned slice contracts.
- Execution Integration Slice 2 — Cockpit execution activity projection completed on 2026-07-15: x-change Cockpit can now read x-journal `execution.result.recorded` entries and project them into dashboard Recent Activity as read-only execution evidence. This uses the existing Cockpit/x-journal reader seam and does not make Cockpit an execution surface or a journal writer.

## Test Coverage Status

- Characterization: strong across the identified danger zone.
- Contract extraction: completed for voucher generation/redemption runtime seams.
- Execution instruction: completed as optional voucher metadata with an implicit legacy default.
- Execution engine introduction: completed as a compatibility surface with context/result DTOs and no runtime wiring change.
- Default driver extraction: completed with `ExecutionDriverContract`, `DefaultExecutionDriver`, engine delegation, and `RedeemVoucher` routing through the engine/default driver.
- Driver registry: completed with singleton registry resolution, package extension registration, fail-closed unknown-driver behavior, and no engine if/else driver selection.
- Architecture stabilization: completed with instruction schema tests, execution result `execution_id` tests, registry-only resolution guards, default-only package registration guard, and no later-driver scaffold guard.
- Settlement-envelope driver: completed with gateway-seam tests for load, readiness, lock, child generation, auto-redemption, fallback claim-voucher generation, registry resolution, and stored-value absence.
- Stored-value driver: completed with gateway-seam tests for activation, no-disbursement ownership claim, spend, over-balance rejection, OTP threshold rejection, replenishment, max-balance rejection, registry resolution, and no stored-value voucher species.
- Driver-composed runtime: completed with tests for step registry registration, unknown-step failure, ordered execution, container/closure resolution, result short-circuiting, fake driver composition, singleton booting, autoloading, and central-engine isolation.
- Integration contract demo: completed with x-change tests for gateway bindings, lifecycle bootstrapper execution payload preservation, runner resolution, settlement-envelope lifecycle execution, and stored-value activate/spend lifecycle execution.
- Execution result projection: completed with a Cockpit feature test proving a lifecycle execution with x-journal handoff appears in dashboard activity as an operator-safe execution row.
- Architecture invariants: x-change now has an executable guard preventing production imports of concrete voucher generation/redemption actions.
- Feature/regression: current voucher and x-change suites cover issuance, redemption, claim, withdrawal, provider failure, and reconciliation.
- Verification: voucher full suite is green as of 2026-06-29 with 381 passed and 28 skipped; x-change package full suite is green with 970 passed and 5 skipped.

## Next Recommended Slice

Execution Engine migration slices 0–9 are now scaffolded. Next work should be an explicit integration-hardening decision, not an automatic new execution-engine slice.

## Open Questions

- Whether settlement-envelope gateway binding should remain in x-change for production or move into a settlement-envelope integration package after provider metadata and recovery behavior are hardened.
- Exact production settlement-envelope metadata fields beyond the current nested baseline: `reference`, `driver`, `readiness_gate`, `child_generation`, `auto_redeem_children`, `fallback_to_claim`, `payload`, `documents`, and `checklist`.
- Whether stored-value gateway binding should remain in x-change for production or move into cash/wallet/dedicated integration once ledger mutation policy is finalized.
- Exact production stored-value metadata fields beyond the current nested baseline: `reference`, `initial_balance`, `max_balance`, `replenishable`, and `otp_required_above`.
- Whether existing `settlement_envelope` and `stored_value` drivers should be decomposed into concrete pipeline steps or remain hand-composed until production behavior stabilizes.
- Production naming/versioning policy for explicit pipeline step lists.
- Whether execution results should be persisted by voucher directly or only consumed by the future x-journal layer remains deferred.
- Exact production shape and timing for voucher-owned executable slice instructions, including purpose labels, unlock conditions, start/end windows, fixed/open/stored-value-like spend semantics, per-slice balances, and per-slice execution results.
- Exact x-change integration slice sequence for binding production settlement-envelope and stored-value gateways while keeping provider, wallet, and cash mechanics outside the voucher execution engine.

## 2026-07-15 Update — Live Cash Transfer Demonstration Path

- Added an x-change-contributed voucher execution driver key: `x_change_live_cash`.
- The driver is registered into voucher's `ExecutionDriverRegistry` by `XChangeServiceProvider`.
- The live transfer path is now demonstrable as `ExecutionEngine -> x_change_live_cash driver -> voucher default compatibility redemption -> x-change payout/reconciliation poller`.
- New lifecycle scenario: `execution_engine_basic_cash_live_transfer`.
- The scenario requires both `--live-provider` and the existing live-provider lifecycle setting before it can perform provider side effects.
- This proves explicit execution instructions can drive a live cash payout without making voucher own provider-specific NetBank/GCash behavior.
- Remaining boundary: this is a live demonstration bridge, not a migration of all cash redemption flows to driver-composed execution.

## 2026-07-15 Update — Execution Result Handoff Pipeline Baseline

- Added an x-change-owned, non-blocking execution-result handoff pipeline.
- The pipeline receives voucher `ExecutionResultData` plus `ExecutionContextData` after driver execution completes.
- Default handoffs are null and report `not_wired` for journal, action, feedback, and Cockpit activity.
- Handoff exceptions are captured as `failed_non_blocking`; they do not alter execution status or reverse provider side effects.
- `ExecutionEngineContractScenarioRunner` now includes `handoffs` in every formatted execution result.
- This is the correct scaffold before wiring x-journal, x-action, x-feedback, and Cockpit as execution-result consumers.

## 2026-07-15 Update — x-journal Execution Result Handoff

- Added `XJournalExecutionResultJournalHandoff` as the first real execution-result consumer.
- Added `ExecutionResultJournalPayloadMapper` to convert voucher `ExecutionResultData` into a sanitized x-journal entry payload.
- Config opt-in: `x-change.execution_result_handoffs.journal = x-journal`.
- Event type: `execution.result.recorded`.
- The journal entry is an audit/event side effect after execution, not an execution prerequisite.
- Handoff failures are still reported as `failed_non_blocking`; they do not mutate voucher execution status, provider reconciliation, or money movement.
- Next recommended slice: Cockpit execution activity projection from the execution-result handoff summary, so operators can see recorded execution evidence.

## 2026-07-15 Update — x-action Execution Result Handoff

- Added `XActionExecutionResultActionHandoff` as an optional execution-result consumer.
- Config opt-in: `x-change.execution_result_handoffs.action = x-action`.
- Event/state key: `execution.result.recorded`.
- The handoff creates only presentation-time continuation plans through x-action `ActionHostComposerContract`.
- It does not execute actions, authorize actions, persist action runs, write journal entries, send feedback, call providers, mutate vouchers, or move money.
- Lifecycle scenario coverage proves `execution.handoffs.action.status = composed` can appear in JSON output when a matching x-action workflow action is registered.
- Report: `reports/010-execution-result-x-action-handoff.md`.
- Next recommended slice: x-feedback notification intent planning from execution results.

## 2026-07-15 Update — x-feedback Execution Result Handoff

- Added `XFeedbackExecutionResultFeedbackHandoff` as an optional execution-result consumer.
- Config opt-in: `x-change.execution_result_handoffs.feedback = x-feedback`.
- Event/intent key: `execution.result.recorded`.
- The handoff uses x-feedback `FeedbackDispatchPreparerContract` only, constrained to `prepare_only` planning.
- It does not dispatch feedback, call delivery providers, persist feedback records, write journal entries, execute actions, call providers, mutate vouchers, or move money.
- Lifecycle scenario coverage proves `execution.handoffs.feedback.status = planned` can appear in JSON output while `performed_side_effect = false`.
- Report: `reports/011-execution-result-x-feedback-handoff.md`.
- Next recommended slice: combined execution-result handoff profile/reporting hardening.

## 2026-07-15 Update — Combined Execution Result Handoff Profile

- Hardened `ExecutionResultHandoffSummaryData::toReportArray()` with aggregate handoff profile reporting.
- The profile includes target statuses, active targets, side-effecting targets, failed targets, and non-blocking status.
- Added lifecycle coverage for x-journal + x-action + x-feedback enabled together.
- Verified combined profile:
  - `journal = recorded`
  - `action = composed`
  - `feedback = planned`
  - `cockpit_activity = not_wired`
  - `performed_side_effect_targets = [journal]`
  - `failed_targets = []`
- The combined profile remains post-execution and non-blocking; it does not alter voucher execution status.
- Report: `reports/012-combined-execution-result-handoff-profile.md`.
- Next recommended slice: Cockpit read-model projection for combined execution handoff status.

## 2026-07-15 Update — Cockpit Execution Handoff Profile Projection

- Extended x-journal-backed Cockpit execution activity rows with safe `metadata.execution_handoff_profile`.
- The projection distinguishes confirmed evidence from configured runtime status:
  - `journal = recorded` is backed by an x-journal `execution.result.recorded` entry.
  - `action = enabled_not_projected` means x-action is configured, but no durable action evidence is projected from the entry.
  - `feedback = enabled_not_projected` means x-feedback is configured, but no durable feedback evidence is projected from the entry.
- Added dashboard feature coverage for default and combined configured profiles.
- No execution behavior, journal writing, action execution, feedback delivery, provider call, voucher mutation, wallet access, or money movement was added.
- Report: `reports/013-cockpit-execution-handoff-profile-projection.md`.
- Next recommended slice: durable action/feedback handoff evidence projection decision.

## 2026-07-15 Update — Durable Action / Feedback Handoff Evidence Decision

- Added explicit durable-evidence decision metadata to Cockpit execution handoff profiles.
- Decision: Cockpit may project `journal = recorded` from x-journal, but must not project exact `action = composed` or `feedback = planned` from runtime configuration alone.
- Reason: the x-journal `execution.result.recorded` entry is persisted before action and feedback handoffs run, so it cannot truthfully contain final action/feedback handoff results.
- Configured x-action/x-feedback remain visible as `enabled_not_projected`.
- Durable evidence now records action/feedback as `deferred` until a future x-action read model, x-feedback read model, journal event, durable handoff evidence record, or post-pipeline summary event exists.
- No new journal event, action execution, feedback delivery, Cockpit mutation, provider call, voucher mutation, wallet access, or money movement was added.
- Report: `reports/014-durable-action-feedback-handoff-evidence-decision.md`.
- Next recommended slice: durable handoff evidence source selection.

## 2026-07-15 Update — Durable Handoff Evidence Source Selection

- Selected the first durable source for exact action/feedback handoff evidence: `post_pipeline_summary_journal_event`.
- Selected target event type: `execution.handoff.summary.recorded`.
- Added config keys:
  - `x-change.execution_result_handoffs.durable_evidence_source`
  - `x-change.execution_result_handoffs.durable_evidence_event_type`
- Cockpit durable evidence metadata now exposes the selected source with `selected_not_implemented`, `writes_now = false`, and `read_only = true`.
- This is a source-selection slice only; no new journal writer, action execution, feedback delivery, Cockpit mutation, provider call, voucher mutation, wallet access, or money movement was added.
- Report: `reports/015-durable-handoff-evidence-source-selection.md`.
- Next recommended slice: post-pipeline handoff summary journal event contract.

## 2026-07-15 Update — Post-Pipeline Handoff Summary Journal Event Contract

- Added `ExecutionResultHandoffSummaryJournalPayloadData`.
- Added `ExecutionResultHandoffSummaryJournalPayloadMapper`.
- Defined the future post-pipeline event contract for `execution.handoff.summary.recorded`.
- The payload includes execution ID, voucher code, correlation ID, aggregate handoff profile, and safe journal/action/feedback/Cockpit handoff evidence.
- The mapper redacts raw provider payloads, raw handoff payloads, wallet/funding data, recipient secrets, OTPs, transport secrets, auth headers, tokens, and other unsafe transport fields.
- No journal writer, event recording, action execution, feedback delivery, Cockpit mutation, provider call, voucher mutation, wallet access, or money movement was added.
- Report: `reports/016-post-pipeline-handoff-summary-journal-event-contract.md`.
- Next recommended slice: post-pipeline handoff summary x-journal writer boundary.

## 2026-07-15 Update — Post-Pipeline Handoff Summary Writer Boundary

- Added `ExecutionResultHandoffSummaryJournalWriterContract`.
- Added `NullExecutionResultHandoffSummaryJournalWriter`.
- Added config seam `x-change.execution_result_handoffs.summary_journal_writer`.
- `ExecutionResultHandoffPipeline` now invokes the summary writer after journal/action/feedback/Cockpit activity handoffs finish.
- The summary writer result is exposed as `handoff_summary_journal` in both the aggregate handoff profile and `ExecutionResultHandoffSummaryData::toReportArray()`.
- Default behavior remains `not_wired`, non-blocking, and side-effect-free.
- No concrete x-journal summary event writer, journal write, action execution, feedback delivery, Cockpit mutation, provider call, voucher mutation, wallet access, or money movement was added.
- Report: `reports/017-post-pipeline-handoff-summary-writer-boundary.md`.
- Next recommended slice: concrete x-journal post-pipeline handoff summary writer.
