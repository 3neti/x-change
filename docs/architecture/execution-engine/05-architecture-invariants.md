# Execution Engine: Architecture Invariants

Status: Canonical guardrails  
Last updated: 2026-08-07

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
21. An explicitly submitted claim destination is authoritative for the initial payout. Execution must reload that persisted destination after model refreshes and must fail closed before a provider call if it differs from the prepared claim audit record.
22. `3neti/money-issuer` is the canonical institution directory for both initial claims and payout corrections. User interfaces select institutions by familiar name; rail-specific routing codes are internal execution data.
23. Contact mobile and payout account are independent values. A settlement rail, including InstaPay, must never cause the contact mobile to replace or populate an account number implicitly.

## Slice 0 Interpretation

The existing x-change `DefaultClaimExecutionFactory`, redemption/withdrawal contracts, execution services, and `WithdrawalPipeline` are current product workflow seams. Their names do not transfer future execution ownership from voucher to x-change.

The current `DefaultSettlementExecutionService` is a readiness-gated pending stub. It does not establish Settlement Envelope as the execution engine and does not authorize envelope execution work in Slice 0.

## Future Test Guards

When their corresponding slices are authorized, add source/dependency tests for concrete voucher imports, default-driver compatibility, registry-only resolution, instruction immutability, structured results, claim/driver separation, and new-driver isolation.
## Claim Evidence Before Execution

- A Pay Code with required claim inputs carries an immutable, versioned evidence-requirements snapshot.
- Required evidence must be complete before an execution driver is selected or invoked.
- The prepared claim attempt and its evidence manifest are durable before any provider call or money movement.
- Provider failure does not delete, return, or reassign captured evidence.
- Evidence belongs to one claim attempt, never merely to the voucher as a whole.
- Settlement Envelopes reference evidence manifests and hashes; they do not embed raw personal or binary evidence.
- OTP secrets and raw KYC provider payloads are never durable claim evidence.
