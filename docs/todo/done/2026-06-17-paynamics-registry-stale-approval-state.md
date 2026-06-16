# 2026-06-17 Paynamics Registry Stale Approval State Handoff

## Context

This slice follows the Paynamics issuer OTP approval UX work from:

```text
docs/todo/done/2026-06-16-paynamics-issuer-otp-approval-ux.md
```

The live web flow is now basically correct:

1. Redeemer claims a Pay Code.
2. Paynamics requires payout OTP.
3. Redeemer waits on `/x/claim/{code}/approval`.
4. Issuer opens `/x/pay-codes/{code}/approval`.
5. Issuer enters OTP.
6. Paynamics disbursement completes.
7. Redeemer reaches success and rider redirect.
8. Issuer returns to the Pay Code surface.

Live voucher `ZYD5` proved the next UX issue:

```text
ZYD5
Redeemed
Needs OTP approval
₱17.00
Issuer action required before payout can complete.

Copy Link
Claim
Approve
View
```

The voucher was already redeemed and disbursed, but the issuer registry still showed the stale approval badge and `Approve` action.

## Root Cause

The Pay Code Registry approval badge is driven by voucher summary `approval` metadata.

That metadata is resolved from the pending Paynamics OTP cache through:

```php
DefaultClaimApprovalStatusResolver
ClaimApprovalPendingOtpStore
VoucherLifecycleService::approvalSummary()
```

Before this patch:

- pending OTP metadata was written when Paynamics requested OTP;
- submitted OTP metadata was written when the issuer entered the OTP;
- approval workflows/session replay payloads were cleared after successful replay;
- the pending OTP cache entry itself was not cleared;
- `VoucherLifecycleService` would still emit `approval.required = true` if the resolver found stale pending OTP metadata, even when the voucher was already redeemed.

That made `/x/pay-codes` show both:

- `Redeemed`
- `Needs OTP approval`

Those states are contradictory from the issuer's point of view. Once the voucher is redeemed, issuer approval is no longer actionable.

## Final Behavior

After successful issuer OTP approval:

- the pending Paynamics OTP cache entry is cleared;
- the submitted OTP cache entry is also cleared;
- voucher summaries suppress approval metadata for redeemed vouchers;
- `/x/pay-codes` no longer renders:
  - `Needs OTP approval`
  - `Issuer action required before payout can complete.`
  - `Approve`

This gives the registry a clean terminal state:

```text
ZYD5
Redeemed
₱17.00

Copy Link
Claim
View
```

## Implementation Details

### 1. Clear Pending OTP State After Completed Approval

`ClaimApprovalOtpController` now receives `ClaimApprovalPendingOtpStore`.

After the approval result has been normalized and the final status is terminal, the controller resolves the approval reference and clears pending/submitted OTP state.

Terminal statuses use the existing controller helper:

```php
success
completed
withdrawn
redeemed
settled
```

Reference resolution checks:

```php
payload.reference_id
result.reference_id
result.approval_metadata.reference_id
result.meta.reference_id
result.disbursement.reference_id
```

Relevant file:

```text
src/Http/Controllers/Web/Claim/ClaimApprovalOtpController.php
```

### 2. Add Explicit Pending OTP Store Forget

`ClaimApprovalPendingOtpStore` now has:

```php
forget(string $referenceId): void
```

It clears both:

```text
x-change:claim-approval:otp:pending:{reference}
x-change:claim-approval:otp:submitted:{reference}
```

This is intentionally concrete to x-change. The upstream `PendingOtpStore` contract in `emi-paynamics` remains unchanged because that contract is only needed by the deferred resolver for requesting and reading submitted OTP.

Relevant file:

```text
src/Support/Claim/ClaimApprovalPendingOtpStore.php
```

### 3. Suppress Approval Metadata For Redeemed Vouchers

`VoucherLifecycleService::approvalSummary()` now short-circuits when:

```php
$voucher->redeemed_at !== null
```

This is a defensive guard. Even if a stale pending OTP cache entry survives for any reason, the registry API should not advertise approval work for a redeemed voucher.

Relevant file:

```text
src/Services/VoucherLifecycleService.php
```

### 4. Registry UI Uses Existing Approval Metadata

No new frontend behavior was required for this specific stale-state fix.

The existing `PayCodeListTable.vue` behavior remains correct:

- show badge/action when `voucher.approval.required === true`;
- hide badge/action when `voucher.approval` is null.

The backend now ensures redeemed vouchers return `approval = null`.

Relevant frontend file from previous scaffold:

```text
resources/js/components/x-change/pay-codes/PayCodeListTable.vue
```

## Tests Added Or Updated

### Voucher Lifecycle Service

Added coverage for stale approval state:

```text
tests/Feature/Services/VoucherLifecycleServiceTest.php
```

Important assertion:

- if the voucher is redeemed, summary status is `redeemed`;
- even if the approval resolver returns pending OTP, summary `approval` is null.

### OTP Store

Added coverage for clearing pending and submitted OTP state:

```text
tests/Unit/Support/Claim/ClaimApprovalPendingOtpStoreTest.php
```

Important assertion:

- `forget(reference)` clears both pending metadata and submitted OTP.

### Approval OTP Controller

Updated replay tests:

```text
tests/Feature/Claim/ClaimApprovalOtpControllerTest.php
```

Important assertions:

- session-backed replay clears pending OTP cache;
- workflow-backed replay clears pending OTP cache.

## Verification

Passed:

```bash
./vendor/bin/pest tests/Feature/Services/VoucherLifecycleServiceTest.php tests/Unit/Support/Claim/ClaimApprovalPendingOtpStoreTest.php --compact
./vendor/bin/pest tests/Feature/Claim/ClaimApprovalOtpControllerTest.php --compact
./vendor/bin/pest tests/Feature/Api/Lifecycle/Vouchers/ListVouchersLifecycleRouteTest.php --compact
npm run test:frontend -- PayCodeListTable
vendor/bin/pint --dirty --format agent
git diff --check
```

Important note:

- Package tests should be run from `packages/x-change`.
- Running package test paths through the host root `php artisan test` can miss the package Pest/Testbench bootstrap.

## Operational Notes

For the live app:

1. Refresh `/x/pay-codes`.
2. If package UI files were changed and not visible, republish with:

```bash
php artisan x-change:install --force --no-migrate
php artisan optimize:clear
```

3. If the browser is still showing stale data, reload the page so the registry fetches fresh voucher summaries.

For voucher `ZYD5`, expected registry behavior after this patch:

- status remains `Redeemed`;
- no `Needs OTP approval` badge;
- no `Issuer action required before payout can complete.` helper;
- no `Approve` action.

## Follow-Up Todos For Claim UI/UX Agent

1. Add a more explicit terminal issuer state.
   The current registry hides approval affordances after redemption. A future detail page could show a small "Payout approved" or "Redeemed and disbursed" operator-facing state.

2. Add automatic registry refresh after issuer approval redirect.
   The issuer is redirected to `/x/pay-codes`, but the page still depends on a fresh API load. If SPA cache behavior appears stale later, force a reload or invalidate the vouchers request after redirect.

3. Consider disabling `Claim` for redeemed vouchers.
   This was not changed in this slice because the existing registry already allowed it. It is separate from the stale approval bug.

4. Keep pending OTP cache cleanup local to successful completion.
   Do not clear pending OTP state on failed OTP verification or `received` status, because the issuer may need retry behavior.

5. Preserve the backend OTP pipeline.
   Do not remove:
   - `SubmitClaimApprovalOtp`
   - `ClaimApprovalOtpController`
   - `ClaimApprovalStatusResolver`
   - `ApprovalStatusData`
   - `ClaimApprovalPendingOtpStore`
   - lifecycle `--approval-pipeline` behavior

## Practical Regression Scenario

1. Generate a Paynamics-backed voucher.
2. Redeem it from a separate browser/session.
3. Confirm redeemer waits on `/x/claim/{code}/approval`.
4. Open issuer approval route `/x/pay-codes/{code}/approval`.
5. Enter Paynamics OTP.
6. Confirm issuer returns to `/x/pay-codes`.
7. Confirm the redeemed row does not show `Needs OTP approval` or `Approve`.
8. Confirm redeemer refresh reaches success and rider redirect.

