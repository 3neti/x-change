# 2026-06-16 Paynamics Issuer OTP Approval UX Handoff

## Context

This slice validates and patches the Paynamics OTP approval path in the web claim UI after lifecycle scenarios proved the provider OTP flow works.

The important domain conclusion is:

- Paynamics payout OTP is issuer-side authorization.
- The redeemer should not enter the OTP.
- The issuer/admin approval surface enters the OTP.
- After issuer approval, the redeemer eventually reaches claim success and rider redirect.
- The issuer/admin surface must not enter the redeemer success/rider journey.

This work was driven by live vouchers including `D8FG`, `D52L`, and earlier `ZZ5M` debugging.

## Final UX Boundary

Redeemer flow:

1. Redeemer submits claim.
2. Paynamics requires payout OTP.
3. Redeemer lands on `/x/claim/{code}/approval`.
4. Page shows waiting copy and hides OTP input.
5. Redeemer refreshes manually for now.
6. Once the issuer has approved and a Paynamics payout reconciliation exists, refresh redirects redeemer to `/x/claim/{code}/success`.
7. Success page proceeds into the configured rider redirect.

Issuer/admin flow:

1. Issuer opens `/x/pay-codes/{code}/approval`.
2. Page renders OTP form using `approval_entry_mode = issuer_otp_entry`.
3. Issuer enters OTP.
4. Backend verifies OTP and replays/completes the Paynamics payout.
5. Issuer is redirected to `/x/pay-codes`.
6. Issuer does not enter `/x/claim/{code}/success` and does not follow the rider redirect.

## Key Implementation Details

### Approval Entry Mode

The Inertia approval page now accepts:

```ts
approval_entry_mode?: 'redeemer_waiting' | 'issuer_otp_entry'
```

Defaults are intentionally redeemer-safe:

- Public claim approval route defaults to `redeemer_waiting`.
- Authenticated pay-code approval route uses `issuer_otp_entry`.

Relevant files:

- `resources/js/components/x-change/approvalPageViewModel.ts`
- `resources/js/pages/x-change/claim/Approval.vue`
- `src/Http/Controllers/Web/Claim/ClaimApprovalPageController.php`
- `routes/web.php`

### Issuer Approval Route

Added authenticated operator route:

```php
GET /x/pay-codes/{code}/approval
name: x-change.pay-codes.approval
```

This reuses the approval page controller, but the controller identifies the route and sends `approval_entry_mode = issuer_otp_entry`.

### Cross-Session Replay Payload

Lifecycle worked because it held replay payload in memory. Web needed cross-session support because:

- Redeemer session submits the claim.
- Issuer/admin session enters the OTP.

The claim submit controller now stores the pending approval workflow and replay payload in `ClaimApprovalWorkflowStoreContract`.

The OTP controller replay lookup order is now:

1. Redeemer session payload via `ClaimApprovalResumePayloadSession`.
2. Cross-session workflow payload via `ClaimApprovalWorkflowStoreContract`.
3. Voucher disbursement metadata fallback.

Relevant files:

- `src/Http/Controllers/Web/Claim/ClaimSubmitController.php`
- `src/Http/Controllers/Web/Claim/ClaimApprovalOtpController.php`

### Approval Page Hydration

The approval page can hydrate pending OTP context from:

1. `CompiledClaimResultSession`.
2. `ClaimApprovalStatusResolver`.
3. Cached approval workflow.

`DefaultClaimApprovalStatusResolver` now considers Paynamics references stored in voucher disbursement metadata:

- `disbursement.reference_id`
- `disbursement.provider_reference`
- `disbursement.provider_tx`
- `disbursement.transaction_id`
- `disbursement.request_id`
- `{voucher_code}-{recipient_identifier}`

Relevant files:

- `src/Http/Controllers/Web/Claim/ClaimApprovalPageController.php`
- `src/Support/Claim/DefaultClaimApprovalStatusResolver.php`

### Stale Redeemer Approval Page Recovery

Live voucher `D8FG` proved a stale redeemer approval page can keep showing `approval_required` even after issuer OTP approval and actual GCash receipt.

The approval page now checks for a real Paynamics provider reconciliation matching the voucher reference. If found, non-JSON redeemer approval requests are redirected to:

```text
/x/claim/{code}/success
```

Before redirecting, the controller replaces stale `compiled_claim_result` session data with a redeemed success payload so the success page does not render the old approval state.

Relevant file:

- `src/Http/Controllers/Web/Claim/ClaimApprovalPageController.php`

### Issuer Redirect Split

Live voucher `D52L` proved the issuer could still land in the redeemer success/rider flow after OTP approval.

Fix:

- Issuer OTP submissions include `redirect_to = pay_codes_index`.
- Backend redirects successful issuer approvals to `x-change.pay-codes.index`.
- Backend also falls back to detecting issuer intent from the referer path:

```text
/x/pay-codes/{code}/approval
```

This protects against stale frontend assets or older forms that do not yet submit `redirect_to`.

Relevant files:

- `resources/js/components/x-change/approvalOtpSubmitAdapter.ts`
- `resources/js/pages/x-change/claim/Approval.vue`
- `src/Http/Controllers/Web/Claim/ClaimApprovalOtpController.php`

## Debug Logging Added

`ClaimApprovalOtpController` logs the final redirect decision:

```text
[ClaimApprovalOtpController] Approval OTP redirect resolved
```

Payload includes:

- `voucher_code`
- `status`
- `provider`
- `reference_id`
- `redirect_to`
- `referer`
- `used_resume_payload`
- `redirect_target`

Expected redirect targets:

- `pay_codes_index` for issuer/admin OTP completion.
- `claim_result_redirector` for redeemer/default completion handling.

## Live Voucher Observations

### D8FG

- OTP was requested.
- Issuer entered OTP.
- Paynamics cash-out was submitted.
- GCash received funds.
- Redeemer approval page remained stale until refreshed.
- Patch added stale approval recovery to redirect redeemer to success after provider reconciliation exists.

### D52L

- OTP was requested.
- Issuer entered OTP.
- Paynamics cash-out was submitted.
- GCash received funds.
- Issuer was incorrectly redirected to redeemer success/rider flow.
- Patch added issuer redirect split and debug logging.

## Tests Added Or Updated

Backend:

- `tests/Feature/Claim/ClaimApprovalPageControllerTest.php`
- `tests/Feature/Claim/ClaimApprovalOtpControllerTest.php`
- `tests/Unit/Support/Claim/DefaultClaimApprovalStatusResolverTest.php`

Frontend:

- `tests/frontend/ClaimApprovalPage.test.ts`
- `tests/frontend/approvalOtpSubmitAdapter.test.ts`
- `tests/frontend/approvalPageViewModel.test.ts`

Verified commands:

```bash
./vendor/bin/pest tests/Feature/Claim/ClaimApprovalOtpControllerTest.php tests/Feature/Claim/ClaimApprovalPageControllerTest.php --compact
npm run test:frontend -- ClaimApprovalPage approvalOtpSubmitAdapter approvalPageViewModel
vendor/bin/pint --dirty --format agent
```

Previous focused suites also passed while building the hydration and resolver changes:

```bash
./vendor/bin/pest tests/Feature/Claim/ClaimApprovalPageControllerTest.php tests/Feature/Claim/ClaimApprovalOtpControllerTest.php tests/Unit/Support/Claim/DefaultClaimApprovalStatusResolverTest.php --compact
```

## Known Follow-Up Todos For Claim UI/UX Agent

1. Add redeemer-side polling or live refresh.
   Current redeemer behavior is passable but manual: refresh approval page to transition to success once issuer approval completes.

2. Replace issuer post-OTP redirect with a proper operator confirmation page if desired.
   Current behavior redirects to `/x/pay-codes`, which is acceptable for now.

3. Consider a dedicated issuer approval page/component.
   Current implementation reuses the claim approval page with `approval_entry_mode`.

4. Revisit Paynamics reconciliation semantics.
   Paynamics may return `GR162`, `Cash Out Pending`, `PRE-DEBIT`, or `WITHHELD` even after GCash receives funds. Current UI recovery relies on local provider reconciliation existence, not a final provider `succeeded` status.

5. Avoid showing redeemer-oriented copy in issuer mode.
   The form behavior is split, but a future dedicated issuer page should use operator language such as "Payout approved" and "Return to pay codes."

6. Keep backend OTP submission APIs intact.
   Do not remove:
   - `SubmitClaimApprovalOtp`
   - `ClaimApprovalOtpController`
   - `ClaimApprovalStatusResolver`
   - `ApprovalStatusData`
   - pending OTP resolver/store behavior
   - lifecycle `--approval-pipeline` behavior

## Practical Test Scenario For Next Agent

1. Generate a Paynamics-backed voucher.
2. Redeem from a separate browser/session using GCash target.
3. Confirm redeemer lands on `/x/claim/{code}/approval` and sees no OTP input.
4. Open issuer route `/x/pay-codes/{code}/approval`.
5. Enter Paynamics OTP.
6. Confirm issuer redirects to `/x/pay-codes`.
7. Refresh redeemer approval page.
8. Confirm redeemer redirects to `/x/claim/{code}/success` and then rider URL.
9. Check `storage/logs/laravel.log` for:

```text
[ClaimApprovalOtpController] Approval OTP redirect resolved
```

The log should show `redirect_target = pay_codes_index` for issuer OTP completion.
