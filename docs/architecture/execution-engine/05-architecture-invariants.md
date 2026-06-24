# Execution Engine: Architecture Invariants

Status: Canonical guardrails  
Last updated: 2026-06-24

1. Voucher owns execution semantics and the future engine, instruction, context/result, driver, and registry types.
2. x-change consumes voucher behavior through contracts once those contracts exist. It must not own voucher execution drivers.
3. Claim UI, form-flow, and the claim compiler collect evidence; they do not determine execution consequences.
4. Valid redemption is the activation point for execution, but execution does not always mean immediate payout.
5. A voucher without an explicit execution instruction must retain pre-migration behavior through an implicit default.
6. New drivers are additive and cannot alter default redeem, withdraw, disbursement, validation, or claim behavior.
7. Drivers are resolved through a registry, not distributed conditional chains.
8. Driver pipelines are execution internals and do not leak into claim UI code.
9. `voucher-pipeline.php` remains the unchanged compatibility lifecycle pipeline until an approved slice deliberately migrates it.
10. Issued execution instructions are immutable unless a separately approved, versioned, audited mutation design exists.
11. Presence and semantics remain separate: `inputs.fields` requires evidence; `validation.*` verifies meaning.
12. Settlement Envelope owns readiness, evidence, approvals, gates, and settlement state. It is a participant, not the engine.
13. Stored value is driver behavior, not automatically a new voucher subclass.
14. New transaction use cases should use instructions and drivers instead of uncontrolled voucher-type proliferation.
15. Every value reservation or movement must be auditable with actor, voucher, driver, amount, recipient, provider reference, status, and failure context.
16. Drivers return structured results; callers do not infer outcomes from incidental side effects alone.
17. Execution visibility is declared and lifecycle truth remains with domain/execution records.
18. Lifecycle scenarios exercise public APIs, contracts, actions, or documented orchestration seams rather than mutating internals.
19. Voucher and x-change repositories remain independently green and independently committed after every slice.
20. No behavior change is silent. Public API, voucher, money movement, validation, and claim UX changes require explicit approval.

## Slice 0 Interpretation

The existing x-change `DefaultClaimExecutionFactory`, redemption/withdrawal contracts, execution services, and `WithdrawalPipeline` are current product workflow seams. Their names do not transfer future execution ownership from voucher to x-change.

The current `DefaultSettlementExecutionService` is a readiness-gated pending stub. It does not establish Settlement Envelope as the execution engine and does not authorize envelope execution work in Slice 0.

## Future Test Guards

When their corresponding slices are authorized, add source/dependency tests for concrete voucher imports, default-driver compatibility, registry-only resolution, instruction immutability, structured results, claim/driver separation, and new-driver isolation.
