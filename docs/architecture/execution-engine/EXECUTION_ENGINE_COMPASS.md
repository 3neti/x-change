# Execution Engine Compass

## Mission

Evolve voucher redemption into a programmable, voucher-owned execution runtime while preserving all existing behavior and keeping x-change as the settlement operating-system/product orchestration layer.

## Current Position

Current slice: Slice 0 — Characterization Baseline  
Status: Completed  
Last updated: 2026-06-24

| Slice | Name | Status |
|---|---|---|
| 0 | Characterization Baseline | Completed |
| 1 | Contract Extraction | Pending approval |
| 2 | Execution Instruction Introduction | Pending |
| 3 | Execution Engine Introduction | Pending |
| 4 | Default Driver Extraction | Pending |
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

## Discoveries

- x-change has existing execution-named workflow contracts, but the target voucher-owned engine, instruction, drivers, and registry do not exist.
- x-change directly imports concrete voucher `GenerateVouchers` and `RedeemVoucher` at exactly two production seams.
- Voucher's post-redemption pipeline validates then disburses; handled provider failures preserve redemption and become pending reconciliation.
- x-change withdrawal is a separate, configurable, traced pipeline with intent-first reconciliation.
- Provider readiness state can influence lifecycle provider selection; deterministic scenarios must carry their provider explicitly.

## Risks

- Global post-redemption behavior couples redemption activation to payout consequence.
- Two different pipeline concepts can be confused during extraction.
- Contract extraction spans separate repositories and requires ordered, independent commits.
- Settlement readiness/pending behavior could be mistaken for implemented envelope execution.
- Full suites depend on linked local packages and normal Testbench filesystem permissions.
- Five unrelated claim-support assertions remain red: four expect the previous approval-metadata key order and one expects scalar/null coercion that current production code does not perform. Changing that production contract is outside Slice 0.

## Architectural Decisions

- Voucher owns future execution semantics.
- x-change owns claim/product orchestration and provider integrations.
- Provider implementations stay outside the voucher engine behind contracts.
- Settlement Envelope is a readiness/authorization participant, not the engine.
- Existing `voucher-pipeline.php` is the Slice 0 compatibility baseline and remains unchanged.
- Planning DTO/class snippets are illustrative until introduced test-first in an authorized slice.

## Test Coverage Status

- Characterization: strong across the identified danger zone.
- Contract extraction: not started.
- Architecture invariants: documented; executable guards begin with the relevant later slices.
- Feature/regression: current voucher and x-change suites cover issuance, redemption, claim, withdrawal, provider failure, and reconciliation.
- Verification: voucher full suite and x-change Feature suite are green; the focused execution baseline is green. The unrelated claim-support mismatch above is documented rather than silently changed.

## Next Recommended Slice

Slice 1 — Contract Extraction, only after explicit human approval. Begin in voucher with failing contract/container-binding tests, then adapt x-change's two concrete dependencies in a separate commit.

## Open Questions

- Exact public namespaces and method signatures for generation/redemption contracts.
- Whether x-change's current redemption workflow contract remains as an adapter or is replaced at its call sites.
- Registry extension API and driver registration ownership.
- Execution result persistence location and correlation strategy.
- Versioning policy for issued execution instructions.
