# X_CHANGE_NAMESPACE_REORG_PLAN.md

## Goal

Scaffold the lifecycle API and related HTTP surface fully under the `LBHurtado\XChange\...` namespace **inside the package**, while preserving current behavior and avoiding logic rewrites.

This plan assumes:

- existing contracts remain the behavioral seams
- existing concrete services remain swappable
- tests must stay green throughout
- the main work is **reorganization and scaffolding**, not business logic changes

The current package already has strong separations across Actions, Contracts, Data, Http, Services, Console, and Tests, which makes this reorganization feasible with low behavioral risk.

---

## Strategic Direction

Instead of keeping HTTP concerns in broad package-level locations like:

- `src/Http/Controllers/Onboarding/...`
- `src/Http/Controllers/PayCode/...`
- `src/Http/Controllers/Redemption/...`

we introduce a **public lifecycle surface** under a dedicated package namespace:

- `LBHurtado\XChange\Lifecycle\Http\Controllers\...`
- `LBHurtado\XChange\Lifecycle\Http\Requests\...`
- `LBHurtado\XChange\Lifecycle\Http\Resources\...`

This gives the package a clean public API boundary that Scramble can document.

---

## Recommended Namespace Layout

## A. Keep existing domain seams
These already exist and should largely remain in place:

- `LBHurtado\XChange\Contracts`
- `LBHurtado\XChange\Actions`
- `LBHurtado\XChange\Services`
- `LBHurtado\XChange\Data`
- `LBHurtado\XChange\Console`
- `LBHurtado\XChange\Models`

These are your behavior layer and integration seams.

## B. Add a dedicated lifecycle API namespace
Introduce:

```text
src/
  Lifecycle/
    Http/
      Controllers/
      Requests/
      Resources/
    Support/
```

Namespace root:

```php
LBHurtado\XChange\Lifecycle\...
```

This is the new public lifecycle API surface.

---

## Recommended Target Tree

```text
src/
  Lifecycle/
    Http/
      Controllers/
        Users/
          CreateUserController.php
          ShowUserController.php
          SubmitUserKycController.php
          ShowUserKycController.php
        Issuers/
          CreateIssuerController.php
          ShowIssuerController.php
          CreateIssuerWalletController.php
          ListIssuerWalletsController.php
        Wallets/
          CreateWalletController.php
          ShowWalletController.php
          ShowWalletBalanceController.php
          ListWalletLedgerController.php
          CreateWalletTopUpController.php
          ShowWalletTopUpController.php
        Pricelist/
          ShowPricelistController.php
          ListPricelistItemsController.php
          EstimateVoucherController.php
        Vouchers/
          CreateVoucherController.php
          ListVouchersController.php
          ShowVoucherController.php
          ShowVoucherByCodeController.php
          ShowVoucherStatusController.php
          CancelVoucherController.php
        Claims/
          StartVoucherClaimController.php
          CompleteVoucherClaimController.php
          SubmitVoucherClaimController.php
          ShowVoucherClaimStatusController.php
        Withdrawals/
          CreateVoucherWithdrawalController.php
          ListVoucherWithdrawalsController.php
          ShowVoucherWithdrawalController.php
        Reconciliations/
          ListReconciliationsController.php
          ShowReconciliationController.php
          ResolveReconciliationController.php
        Events/
          ListEventsController.php
          ShowEventController.php
          ShowIdempotencyKeyController.php
      Requests/
        Users/
          CreateUserRequest.php
          SubmitUserKycRequest.php
        Issuers/
          CreateIssuerRequest.php
          CreateIssuerWalletRequest.php
        Wallets/
          CreateWalletRequest.php
          CreateWalletTopUpRequest.php
        Pricelist/
          EstimateVoucherRequest.php
        Vouchers/
          CreateVoucherRequest.php
          CancelVoucherRequest.php
        Claims/
          StartVoucherClaimRequest.php
          CompleteVoucherClaimRequest.php
          SubmitVoucherClaimRequest.php
        Withdrawals/
          CreateVoucherWithdrawalRequest.php
        Reconciliations/
          ResolveReconciliationRequest.php
      Resources/
        Common/
          ErrorResource.php
          MetaResource.php
        Users/
          UserResource.php
          UserKycResource.php
        Issuers/
          IssuerResource.php
          IssuerWalletResource.php
        Wallets/
          WalletResource.php
          WalletBalanceResource.php
          WalletLedgerEntryResource.php
          WalletTopUpResource.php
        Pricelist/
          PricelistResource.php
          PricelistItemResource.php
          VoucherEstimateResource.php
        Vouchers/
          VoucherResource.php
          VoucherStatusResource.php
        Claims/
          VoucherClaimStartResource.php
          VoucherClaimCompletionResource.php
          VoucherClaimSubmissionResource.php
          VoucherClaimStatusResource.php
        Withdrawals/
          VoucherWithdrawalResource.php
        Reconciliations/
          ReconciliationResource.php
        Events/
          EventResource.php
          IdempotencyKeyResource.php
```

---

## Existing Classes to Reuse Instead of Rewriting

The current package already has reusable actions and contracts.

### Actions already in place
These should remain the behavioral engines:

- `Actions\Onboarding\OnboardIssuer`
- `Actions\Onboarding\OpenIssuerWallet`
- `Actions\PayCode\EstimatePayCodeCost`
- `Actions\PayCode\GeneratePayCode`
- `Actions\Redemption\PreparePayCodeRedemptionFlow`
- `Actions\Redemption\LoadPayCodeRedemptionCompletionContext`
- `Actions\Redemption\SubmitPayCodeClaim`
- `Actions\Redemption\RedeemPayCode`
- `Actions\Redemption\WithdrawPayCode`
- `Actions\Wallet\GetWalletBalance`
- `Actions\Reconciliation\ReconcileDisbursement`
- `Actions\Reconciliation\RecordDisbursementReconciliation`

### Contracts already in place
These are the key interchange points and should not be collapsed:

- `IssuerOnboardingContract`
- `WalletProvisioningContract`
- `PricingServiceContract`
- `PayCodeIssuanceContract`
- `RedemptionFlowPreparationContract`
- `RedemptionCompletionContextContract`
- `ClaimExecutionFactoryContract`
- `RedemptionExecutionContract`
- `WithdrawalExecutionContract`
- `DisbursementReconciliationContract`
- `DisbursementStatusFetcherContract`
- `DisbursementStatusResolverContract`
- `IdempotencyStoreContract`
- `AuditLoggerContract`
- `VoucherAccessContract`
- `WalletAccessContract`

### Concrete services already in place
These should remain the default implementations behind the contracts:

- `DefaultIssuerOnboardingService`
- `DefaultWalletProvisioningService`
- `InstructionBackedPricingService`
- `PayCodeIssuanceService`
- `DefaultRedemptionFlowPreparationService`
- `DefaultRedemptionCompletionContextService`
- `DefaultClaimExecutionFactory`
- `DefaultRedemptionExecutionService`
- `DefaultWithdrawalExecutionService`
- `DefaultDisbursementReconciliationService`
- `DefaultDisbursementStatusFetcherService`
- `DefaultDisbursementStatusResolverService`

The lifecycle namespace should wrap these, not replace them.

---

## Reorganization Principle

### New lifecycle HTTP layer
Owns:
- route-facing controllers
- request validation
- response resource serialization
- Scramble-friendly naming
- public API boundary

### Existing Actions / Contracts / Services
Own:
- business logic
- orchestration
- integrations
- lifecycle semantics
- idempotency / reconciliation behavior

This preserves your current design and avoids logic churn.

---

## Route Strategy

Create a dedicated lifecycle route file under the package:

```text
routes/lifecycle-api.php
```

and mount it from the service provider.

Suggested public prefix:

```text
/api/x/v1/...
```

Suggested route naming prefix:

```text
api.x.v1.*
```

This route file should point to the new package controllers under:

```php
LBHurtado\XChange\Lifecycle\Http\Controllers\...
```

---

## Request Strategy

All new public lifecycle endpoints should use package-local request classes under:

```php
LBHurtado\XChange\Lifecycle\Http\Requests\...
```

These requests should:

- validate public payload shape
- remain thin
- avoid embedding business logic
- document nested shapes for Scramble where needed

Where existing request classes already exist and are sufficient, either:
- move them into the lifecycle namespace, or
- create thin lifecycle wrappers and deprecate the old location later

---

## Resource Strategy

Introduce package-local resources under:

```php
LBHurtado\XChange\Lifecycle\Http\Resources\...
```

These resources should normalize the public contract and avoid exposing raw internal DTO structure directly.

Recommended envelope:

### Success
```json
{
  "data": {},
  "meta": {}
}
```

### Collection
```json
{
  "data": [],
  "meta": {}
}
```

### Error
```json
{
  "error": {
    "code": "string",
    "message": "string",
    "details": {},
    "correlation_id": "string"
  }
}
```

---

## Naming Rules

### Controllers
Use:
- `Create...Controller`
- `Show...Controller`
- `List...Controller`
- `Start...Controller`
- `Complete...Controller`
- `Submit...Controller`
- `Resolve...Controller`
- `Cancel...Controller`

### Requests
Use:
- `Create...Request`
- `Submit...Request`
- `Estimate...Request`
- `Complete...Request`
- `Resolve...Request`
- `Cancel...Request`

### Resources
Use:
- `...Resource` for single-object payloads
- `...Resource::collection(...)` for lists

### Route params
Use:
- `{user}`
- `{issuer}`
- `{wallet}`
- `{voucher}`
- `{reconciliation}`
- `{event}`

Use scalar params only where model binding does not apply:
- `{code}`
- `{key}`

### JSON fields
Use snake_case only.

---

## Migration Plan

## Phase 1 — Add the lifecycle namespace without moving logic
Create the new directories and empty classes:

- `src/Lifecycle/Http/Controllers/...`
- `src/Lifecycle/Http/Requests/...`
- `src/Lifecycle/Http/Resources/...`

At this stage, controllers can simply delegate to existing Actions and Services.

## Phase 2 — Add a dedicated lifecycle route file
Create package route file:

- `routes/lifecycle-api.php`

Register it in `XChangeServiceProvider`.

Do not remove existing routes yet.

## Phase 3 — Map the first lifecycle slice
Implement only the first public endpoints:

- create issuer
- open issuer wallet
- get wallet balance
- show pricelist
- estimate voucher
- generate voucher
- prepare claim
- submit claim
- show reconciliation

These should delegate to existing actions and contracts.

## Phase 4 — Add resource serialization
Wrap current data objects in lifecycle resources.

Do not rewrite DTOs unless necessary.

## Phase 5 — Add lifecycle-specific tests
Add new feature tests for the lifecycle route surface under a package lifecycle namespace.

## Phase 6 — Run full suite repeatedly
After each vertical slice:
- run targeted tests
- run the full relevant Pest suite
- only then continue

## Phase 7 — Deprecate old HTTP locations later
Only after the lifecycle namespace is stable should you consider deprecating:

- `src/Http/Controllers/...`
- `src/Http/Requests/...`

There is no need to remove them immediately.

---

## Test Strategy

The safest approach is **reorganization without logic movement**.

### Before any refactor
Run:
- current feature API tests
- current unit tests
- current lifecycle console tests

### During refactor
After each namespace slice:
- add focused tests for new lifecycle routes
- keep old tests green
- verify no service bindings broke

### After first slice
Run the full package suite.

The current package tree already shows strong test coverage across:
- Actions
- API
- Onboarding
- Redemption
- Reconciliation
- Services
- Console lifecycle scenarios

That existing suite is your safety rail and should remain the main guard against accidental logic drift.

---

## Suggested First Scaffold Slice

Start with these package lifecycle classes only:

### Controllers
- `Lifecycle\Http\Controllers\Issuers\CreateIssuerController`
- `Lifecycle\Http\Controllers\Issuers\CreateIssuerWalletController`
- `Lifecycle\Http\Controllers\Wallets\ShowWalletBalanceController`
- `Lifecycle\Http\Controllers\Pricelist\ShowPricelistController`
- `Lifecycle\Http\Controllers\Pricelist\EstimateVoucherController`
- `Lifecycle\Http\Controllers\Vouchers\CreateVoucherController`
- `Lifecycle\Http\Controllers\Claims\StartVoucherClaimController`
- `Lifecycle\Http\Controllers\Claims\SubmitVoucherClaimController`
- `Lifecycle\Http\Controllers\Reconciliations\ShowReconciliationController`

### Requests
- `Lifecycle\Http\Requests\Issuers\CreateIssuerRequest`
- `Lifecycle\Http\Requests\Issuers\CreateIssuerWalletRequest`
- `Lifecycle\Http\Requests\Pricelist\EstimateVoucherRequest`
- `Lifecycle\Http\Requests\Vouchers\CreateVoucherRequest`
- `Lifecycle\Http\Requests\Claims\StartVoucherClaimRequest`
- `Lifecycle\Http\Requests\Claims\SubmitVoucherClaimRequest`

### Resources
- `Lifecycle\Http\Resources\Issuers\IssuerResource`
- `Lifecycle\Http\Resources\Issuers\IssuerWalletResource`
- `Lifecycle\Http\Resources\Wallets\WalletBalanceResource`
- `Lifecycle\Http\Resources\Pricelist\PricelistResource`
- `Lifecycle\Http\Resources\Pricelist\VoucherEstimateResource`
- `Lifecycle\Http\Resources\Vouchers\VoucherResource`
- `Lifecycle\Http\Resources\Claims\VoucherClaimStartResource`
- `Lifecycle\Http\Resources\Claims\VoucherClaimSubmissionResource`
- `Lifecycle\Http\Resources\Reconciliations\ReconciliationResource`

This gives you a usable first public lifecycle API without destabilizing the package.

---

## What Not To Do

Do not:
- move business logic into the new controllers
- rewrite service classes just because namespaces changed
- collapse contracts into concrete implementations
- expose internal web or form-flow routes as public lifecycle API
- attempt a big-bang rename of every current HTTP class at once
- remove existing controllers before the lifecycle surface is proven

---

## Bottom Line

Yes — a full reorganization is possible, and the package is already structured well enough to support it.

But the safest and strongest version is:

1. add a new `LBHurtado\XChange\Lifecycle\...` public HTTP namespace
2. keep current contracts, actions, services, and DTOs as the behavior layer
3. wire the new lifecycle controllers to existing concrete classes
4. add tests before removing anything old
5. let Scramble target the new lifecycle route surface only

That gives you a clean package-native lifecycle API while preserving your current working internals.
