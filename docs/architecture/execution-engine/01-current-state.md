# Execution Engine: Current State

Status: Canonical Slice 0 baseline  
Last verified: 2026-06-24

## Scope

This document describes behavior that exists today in `3neti/voucher` and `3neti/x-change`. Names proposed for later slices are not evidence of current implementation.

## Architectural Summary

```text
x-change claim UI/API
    -> SubmitCompiledFormClaim / SubmitPayCodeClaim
    -> DefaultClaimExecutionFactory
       -> RedeemPayCode -> DefaultRedemptionExecutionService
          -> DefaultRedemptionProcessorService
          -> voucher RedeemVoucher
       OR
       -> WithdrawPayCode -> DefaultWithdrawalExecutionService
          -> DefaultWithdrawalProcessorService -> WithdrawalPipeline
    -> provider payout and reconciliation side effects
```

Voucher issuance is initiated by x-change but executed by the voucher package:

```text
PayCodeIssuanceService
    -> voucher GenerateVouchers
    -> VouchersGenerated
    -> HandleGeneratedVouchers
    -> post-generation pipeline
    -> CreateCashEntities -> MintCash -> mint-cash pipeline
```

## Voucher Generation

- x-change entry points include `routes/api.php` and `Lifecycle/Http/Controllers/Vouchers/CreateVoucherController.php`.
- `LBHurtado\XChange\Services\PayCodeIssuanceService` normalizes input, hydrates `LBHurtado\Voucher\Data\VoucherInstructionsData`, authenticates the issuer for the call, and directly invokes `LBHurtado\Voucher\Actions\GenerateVouchers`.
- `GenerateVouchers` creates voucher records with the FrittenKeeZ facade, stores cleaned instructions under `metadata.instructions`, applies timing defaults, and dispatches `LBHurtado\Voucher\Events\VouchersGenerated`.
- `LBHurtado\Voucher\Listeners\HandleGeneratedVouchers` runs unprocessed vouchers through `voucher-pipeline.post-generation` inside a database transaction.
- `CreateCashEntities` invokes `LBHurtado\Voucher\Services\MintCash`. Its pipeline creates a `Cash` entity, charges the owner through the wallet customer interface, and attaches the cash entity to the voucher.
- `MarkAsProcessed` persists `processed = true`.

## Voucher Redemption

- `LBHurtado\Voucher\Actions\RedeemVoucher` calls the underlying voucher facade with a `Contact` and metadata under the `redemption` key.
- Missing, expired, unstarted, and already-redeemed vouchers are soft failures returning `false`; unexpected exceptions bubble.
- The voucher model observer delegates the `redeemed` lifecycle event to `LBHurtado\Voucher\Handlers\HandleRedeemedVoucher`.
- `HandleRedeemedVoucher` runs `voucher-pipeline.post-redemption`, dispatches `DisbursementRequested` after pipeline completion, and dispatches wallet `DisbursementFailed` before rethrowing an uncaught pipeline error.
- Provider failures handled inside `DisburseCash` do not reverse redemption. They persist a pending disbursement and emit failure events for reconciliation.

## Claim UX and API

Public web claim routes are under `/x/claim`; public API claim routes are under the configured `/api/x/v1/pay-codes/{code}/claim/*` prefix.

- `ClaimStartController`, `ClaimExperienceController`, `ClaimCompleteController`, and the compiler/form-flow actions prepare and collect evidence.
- `ClaimSubmitController` and `SubmitCompiledFormClaim` submit the normalized payload to `SubmitPayCodeClaim`.
- `SubmitPayCodeClaim` enriches named-slice data, applies provider readiness checks, asks `ClaimExecutionFactoryContract` for an executor, normalizes the result, and records the claim.
- Claim UI, compiler, and form-flow prepare inputs. They do not own voucher redemption semantics.

## Redeem Versus Withdraw

`LBHurtado\XChange\Services\DefaultClaimExecutionFactory` owns the current branch:

- Settlement capability returns the current `SettlementExecutionContract` implementation.
- Non-disbursable capability is rejected.
- An open-slice divisible voucher selects `WithdrawPayCode` even before initial redemption.
- An already-redeemed voucher selects withdrawal only when `canWithdraw()` is true.
- Otherwise it selects the injected `RedeemPayCode` executor.

The payload currently does not select this branch. Voucher flow capability and voucher lifecycle state do.

## Existing x-change Execution Abstractions

| Current abstraction | Current role | Future mapping |
|---|---|---|
| `DefaultClaimExecutionFactory` | Chooses settlement, redeem, or withdraw claim executor | Product orchestration consumer of future voucher contracts; not the future engine |
| `RedemptionExecutionContract` | x-change redeem workflow seam | May adapt to a voucher-owned execution contract |
| `WithdrawalExecutionContract` | x-change withdrawal workflow seam | Remains x-change workflow/payout orchestration unless later architecture explicitly moves semantics |
| `DefaultRedemptionExecutionService` | Resolve context, validate, process, shape result | Compatibility adapter candidate |
| `DefaultWithdrawalExecutionService` | Validate and process withdrawal | Existing product workflow, not a voucher execution driver |
| `WithdrawalPipeline` | Runs configurable x-change withdrawal steps with traces | Existing withdrawal orchestration; not `voucher-pipeline.php` and not the future engine |

These classes exist now. `ExecutionInstructionData`, `ExecutionEngine`, driver contracts, and a driver registry do not.

## Disbursement and Providers

The current redeem path disburses in voucher's `Pipelines/RedeemedVoucher/DisburseCash` through `LBHurtado\EmiCore\Contracts\PayoutProvider`.

- A successful response withdraws the cash wallet value, stores provider metadata, and emits `VoucherDisbursementSucceeded`.
- A failed response/exception stores `pending` with `requires_reconciliation`, emits `VoucherDisbursementFailed`, and lets the redemption remain successful.
- x-change listeners `RecordSuccessfulVoucherDisbursement` and `RecordFailedVoucherDisbursement` create reconciliation records.
- The withdrawal path calls providers through `WithdrawalDisbursementExecutor`, records intent before the provider call, then records the resolved response or exception.
- Provider-specific implementations such as Paynamics and Netbank remain outside the voucher runtime. Voucher invokes the provider contract.
- Payment collection webhooks are parsed and coordinated by x-change; scheduled reconciliation checks unresolved disbursements.

## `voucher-pipeline.php`

The unchanged compatibility configuration is:

| Bucket | Ordered steps | Role |
|---|---|---|
| `updated` | empty | Reserved lifecycle hook |
| `post-generation` | `ValidateStructure`, `PopulateSettlementFields`, `NormalizeMetadata`, `RunFraudChecks`, `ApplyUsageLimits`, `CreateCashEntities`, `NotifyBatchCreator`, `LogAuditTrail`, `MarkAsProcessed`, `TriggerPostGenerationWorkflows` | Global generation lifecycle |
| `mint-cash` | `CheckBalance`, `EscrowAction`, `PersistCash` | Cash creation and attachment; the first two are currently pass-through placeholders |
| `post-redemption` | `ValidateRedeemerAndCash`, `ValidateRedemptionContract`, `DisburseCash` | Redemption validation and payout consequence |

This file is global/default lifecycle configuration. Slice 0 does not redesign it.

## Redemption Contract Validation

`ValidateRedemptionContract` calls `RedemptionContractEngine`, which extracts redeemer evidence and runs registered rule validators. The invariant visible in implementation and tests is:

```text
inputs.fields = required evidence presence
validation.* = semantic verification
```

`RequiredInputFieldsValidator` protects presence. Signature, selfie, location, OTP, time, and face-match validators protect semantic rules. Blocking issues throw `VoucherRedemptionContractViolationException`; warnings are persisted and the pipeline continues.

## Package Boundary

| Concern | Current owner |
|---|---|
| Voucher identity, instructions, generation, redemption, contract validation | voucher |
| Global voucher lifecycle pipelines and redeem disbursement step | voucher |
| Claim UI/API/compiler/form-flow coordination | x-change / form-flow |
| Redeem/withdraw claim selection | x-change |
| Withdrawal orchestration and reconciliation | x-change |
| Provider-specific payout adapters | provider packages / host bindings |
| Settlement readiness, evidence, approvals, gates | settlement-envelope |
| Lifecycle scenario runner | x-change |

## Direct Concrete Dependencies

Two production dependencies require a later contract seam:

- `src/Services/PayCodeIssuanceService.php` directly imports and runs voucher `GenerateVouchers`.
- `src/Services/DefaultRedemptionProcessorService.php` directly imports and runs voucher `RedeemVoucher`.

No contract extraction is authorized in Slice 0.

## Coverage and Risks

Current tests protect issuance, facade redemption outcomes, pipeline order, presence and semantic validation, claim submission, branch selection, withdrawal steps, provider success/failure, pending reconciliation, and lifecycle scenarios. Slice 0 adds exact generation pipeline order and generated `processed`/cash side-effect assertions.

Active risks:

- Redemption and disbursement are coupled in the global post-redemption pipeline.
- x-change directly depends on concrete voucher actions at two seams.
- There are two different pipelines: voucher lifecycle configuration and x-change withdrawal orchestration.
- Settlement execution currently exists as an x-change readiness/pending stub; it must not be mistaken for the target voucher-owned engine.
- Provider and wallet tests rely on Testbench configuration and can become order-sensitive when provider readiness state leaks across grouped scenarios.

## Non-goals

This baseline introduces no execution contracts, instructions, engine, drivers, registry, public API changes, validation changes, payout changes, claim UX changes, or `voucher-pipeline.php` changes.
