# Execution Engine: Evolution Plan

Status: Slice 0 complete; later slices require human approval  
Last updated: 2026-06-24

## Migration Rule

This is a test-driven extraction, not a rewrite. Each slice must preserve voucher generation, redemption, claim submission, branch selection, payout behavior, public APIs, lifecycle scenarios, and claim UX. Each repository must be green independently at every checkpoint.

## Slices

| Slice | Purpose | Required outcome |
|---|---|---|
| 0 | Characterization Baseline | Canonical docs and tests freeze current behavior |
| 1 | Contract Extraction | Voucher generation/redemption contracts bind existing implementations; x-change consumes them |
| 2 | Execution Instruction | Optional instruction data with legacy-compatible implicit default |
| 3 | Execution Engine | Context/result/runtime introduced behind current behavior |
| 4 | Default Driver | Current redemption pipeline delegated through compatibility driver |
| 5 | Driver Registry | Extensible registry resolves only the default driver initially |
| 6 | Architecture Stabilization | Invariants, contracts, persistence, visibility, and tests reviewed without features |
| 7 | Settlement Envelope Driver | Readiness-gated authority execution after stable default runtime |
| 8 | Stored Value Driver | Ownership activation and spending semantics without affecting default vouchers |
| 9 | Driver-Composed Runtime | Optional modular pipeline composition after demonstrated need |

## Slice 0 Result

- Planning scaffolds were reconciled against both repositories.
- Current x-change execution abstractions were classified as product workflow seams, not the target engine.
- Generation pipeline order and generated side effects were strengthened in voucher tests.
- Existing claim, branch, validation, disbursement, pending, and legacy coverage was verified.
- `voucher-pipeline.php` and all money-movement production behavior remained unchanged.

## Slice 1 Entry Criteria

Do not begin until a human approves Slice 1. At that point:

1. Re-read the Compass and canonical documents.
2. Confirm both worktrees and baselines.
3. Write failing voucher binding/contract tests first.
4. Add the smallest voucher contracts around current implementations.
5. Change x-change's two concrete dependencies only after voucher is green.
6. Commit voucher and x-change independently.

## Rollback and Commit Boundaries

Every slice must be independently reversible. Never mix voucher and x-change in one commit. Preserve unrelated worktree/index changes and stage only the files belonging to the current coherent increment.

## Stop Conditions

Stop before proceeding when source contradicts canonical behavior, package ownership is unclear, public behavior would change, production money movement or claim UX would change, unrelated tests fail, or a later slice would be required to complete the current one.
