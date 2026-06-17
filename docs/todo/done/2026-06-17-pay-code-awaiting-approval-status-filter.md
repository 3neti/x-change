# 2026-06-17 Pay Code Awaiting Approval Status And Filter Handoff

## Context

This slice follows the Paynamics issuer OTP approval UX work and the Pay Code Registry approval affordance.

The product issue was that Paynamics OTP-pending vouchers could appear in the issuer registry as:

```text
Redeemed
Needs OTP approval
```

That was technically explainable because the voucher can already be consumed internally while waiting for issuer-side Paynamics OTP authorization. However, it is confusing for issuer/admin users because `Redeemed` reads as already disbursed.

Final UX rule:

- canonical lifecycle status remains internal and unchanged;
- issuer-facing status uses a presentation status;
- Paynamics OTP-pending vouchers display as `Awaiting approval`;
- terminal completed vouchers display as `Redeemed`;
- users can filter the registry by `Awaiting approval`.

## Important Correction To Previous Stale-State Assumption

The earlier stale approval cleanup slice added a guard that treated `redeemed_at` as enough reason to suppress approval metadata.

That is not correct for this provider flow.

For Paynamics OTP:

- `redeemed_at` may be set as an internal consumption marker;
- the payout may still be waiting for issuer OTP;
- the issuer still needs the approval action.

Therefore `VoucherLifecycleService::approvalSummary()` must not short-circuit solely because `redeemed_at` is present.

The correct stale-state protection is:

- successful OTP replay clears the pending/submitted OTP cache;
- active pending approval metadata is allowed to surface even when `redeemed_at` exists;
- UI presentation uses `display_status = awaiting_approval` while approval is pending.

## Final Behavior

Pending issuer OTP voucher:

```text
R6DD
Awaiting approval
₱18.00
Issuer OTP approval required before payout can complete.

Copy Link
Claim
Approve
View
```

Completed voucher:

```text
CNC7
Redeemed
₱20.00

Copy Link
Claim
View
```

The registry filter now includes:

```text
All statuses
Awaiting approval
Active
Redeemed
Expired
Pending
Failed
```

Selecting `Awaiting approval` shows vouchers whose presentation status is `awaiting_approval` and excludes ordinary redeemed vouchers.

## Implementation Details

### Presentation Status Contract

`VoucherLifecycleService` now emits both:

```php
'status' => $status,
'display_status' => $this->displayStatus($status, $approval),
```

Rules:

- `status` remains the canonical lifecycle status.
- `display_status` is presentation-only.
- if `approval.required === true`, `display_status` is `awaiting_approval`;
- otherwise `display_status` mirrors `status`.

This is emitted for summary and detail payloads.

Relevant files:

```text
src/Services/VoucherLifecycleService.php
src/Lifecycle/Http/Resources/Vouchers/VoucherSummaryResource.php
src/Lifecycle/Http/Resources/Vouchers/VoucherDetailResource.php
```

### Registry Row UX

`PayCodeListTable.vue` now passes the presentation status to `PayCodeStatusBadge`.

The duplicate `Needs OTP approval` badge was removed because it made the row feel like it had two competing statuses.

The action helper remains:

```text
Issuer OTP approval required before payout can complete.
```

The `Approve` button remains visible while `voucher.approval.required === true`.

Relevant file:

```text
resources/js/components/x-change/pay-codes/PayCodeListTable.vue
```

### Status Badge

`PayCodeStatusBadge.vue` now supports:

```text
awaiting_approval -> Awaiting approval
```

It uses the existing outline badge variant.

Relevant file:

```text
resources/js/components/x-change/pay-codes/PayCodeStatusBadge.vue
```

### Detail Page

The Pay Code detail page also prefers `display_status` for the header and summary status badges.

This keeps `/x/pay-codes` and `/x/pay-codes/{code}` consistent.

Relevant file:

```text
resources/js/pages/x-change/pay-codes/Show.vue
```

### Awaiting Approval Filter

`PayCodeFilters.vue` now includes an `Awaiting approval` option.

`Index.vue` accepts `awaiting_approval` as a filter value and infers status in this order:

1. `voucher.display_status`
2. `voucher.approval.required === true` fallback
3. canonical `voucher.status`
4. `redeemed_at`
5. expiry
6. active fallback

Relevant files:

```text
resources/js/components/x-change/pay-codes/PayCodeFilters.vue
resources/js/pages/x-change/pay-codes/Index.vue
```

## Tests Added Or Updated

### Backend

`VoucherLifecycleServiceTest.php`

- pending Paynamics approval emits `display_status = awaiting_approval`;
- redeemed/internal-consumed voucher with pending OTP still emits approval metadata;
- vouchers without approval mirror `display_status` from canonical `status`;
- detail responses include display status.

`ListVouchersLifecycleRouteTest.php`

- summary API resource includes `display_status`.

`ShowVoucherByCodeLifecycleRouteTest.php`

- detail API resource includes `display_status`;
- detail API resource includes `approval`.

### Frontend

`PayCodeListTable.test.ts`

- approval-pending voucher renders the awaiting approval presentation status;
- duplicate `Needs OTP approval` text is absent;
- helper text and `Approve` action remain.

`PayCodeIndexPage.test.ts`

- selecting the `Awaiting approval` filter keeps an approval-pending voucher visible;
- ordinary redeemed vouchers are excluded by that filter.

## Verification

Commands run from `packages/x-change`:

```bash
./vendor/bin/pest tests/Feature/Services/VoucherLifecycleServiceTest.php tests/Feature/Api/Lifecycle/Vouchers/ListVouchersLifecycleRouteTest.php tests/Feature/Api/Lifecycle/Vouchers/ShowVoucherByCodeLifecycleRouteTest.php --compact
npm run test:frontend -- PayCodeListTable PayCodeIndexPage
```

Results:

```text
17 PHP tests passed
5 frontend tests passed
```

Commands run from the sandbox root:

```bash
vendor/bin/pint --dirty --format agent
git diff --check
```

Results:

```text
Pint passed
git diff --check passed
```

## Host App Sync Note

Changes were made in `packages/x-change`.

To see the package UI in the host sandbox, run:

```bash
php artisan x-change:install
```

Then keep the Vite dev server running or rebuild assets as usual.
