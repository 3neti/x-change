# OTP Authorization Adapter Plan — Paynamics Payout OTP in x-change Claim UI

## 1. Updated Objective

The goal is no longer to create a new web endpoint or move the claim UI into the Lifecycle Scenario Runner path.

The goal is to make the existing x-change claim approval UI endpoint correctly invoke the concrete Paynamics payout OTP authorization adapter.

Current web endpoint already exists:

```text
POST /x/claim/{code}/approval/otp
```

Current controller already exists:

```php
LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimApprovalOtpController
```

Current frontend already expects this route.

Therefore, the work is now:

```text
Claim UI
→ existing ClaimApprovalOtpController
→ SubmitClaimApprovalOtp
→ provider-bound approval OTP authorizer
→ concrete Paynamics OTP adapter
→ resume claim / payout
```

---

## 2. New Guiding Principle

Do not create a parallel controller.

Do not create a new UI endpoint unless API/JSON parity is needed later.

Do not call Paynamics from Vue.

Do not make Claim UI depend on the Lifecycle Scenario Runner.

Instead:

```text
Patch the existing web approval OTP path.
Make SubmitClaimApprovalOtp resolve the correct provider adapter.
Make Paynamics the concrete provider implementation behind the contract.
```

---

## 3. Current Existing Flow

The existing web route is:

```php
Route::post('claim/{code}/approval/otp', ClaimApprovalOtpController::class)
    ->name('x-change.claim.approval.otp');
```

The existing controller flow is:

```text
ClaimApprovalOtpController
→ find voucher by code
→ validate otp/reference_id/provider
→ SubmitClaimApprovalOtp::handle()
→ CompiledClaimResultSession::put()
→ ClaimApprovalOtpResultRedirector::redirect()
```

This means the web UI already has the correct surface.

The missing piece is the provider adapter behind:

```php
SubmitClaimApprovalOtp
```

---

## 4. Correct Integration Target

The main seam is:

```php
LBHurtado\XChange\Actions\Claim\SubmitClaimApprovalOtp
```

This action should become the stable adapter entry point for claim UI OTP submission.

It should:

```text
- accept voucher + OTP payload
- normalize provider name
- resolve the configured/bound provider authorizer
- delegate OTP authorization to provider-specific adapter
- return normalized claim result
```

The concrete Paynamics adapter should not live in the controller.

---

## 5. Package Responsibility

## x-change

Owns:

```text
- claim approval route
- claim approval controller
- SubmitClaimApprovalOtp action
- provider authorizer resolution
- claim result redirect/session behavior
- claim UI integration
```

## emi-core

Owns:

```text
- provider-neutral payout OTP authorization contract if needed
- generic DTOs/interfaces shared by EMI providers
```

## emi-paynamics

Owns:

```text
- Paynamics-specific OTP API call
- Paynamics-specific request/response mapping
- Paynamics cash-out OTP verification/authorization behavior
```

## lifecycle scenario runner

Owns:

```text
- CLI simulation
- manual OTP prompt for live testing
```

It should eventually consume the same action/adapter path, but it is not the UI integration point.

---

## 6. What To Do With Previous Scaffolds

Keep these:

```php
LBHurtado\XChange\Actions\Claim\InitiateClaimApproval
LBHurtado\XChange\Actions\Claim\VerifyClaimApprovalOtp
```

They are thin, tested application seams.

But do not build new web controllers around them yet.

Current canonical web path remains:

```php
ClaimApprovalOtpController
→ SubmitClaimApprovalOtp
```

Later, if duplication becomes obvious:

```text
VerifyClaimApprovalOtp
SubmitClaimApprovalOtp
DefaultClaimApprovalExecutionService
```

can be consolidated.

For now, keep them because tests are green and they do not interfere.

---

## 7. Immediate Implementation Strategy

### Slice 1 — Inspect and lock `SubmitClaimApprovalOtp`

Find:

```php
src/Actions/Claim/SubmitClaimApprovalOtp.php
```

Confirm:

```text
- what contract it calls
- how it resolves provider
- whether it already supports provider-bound authorizers
- what result shape it returns
```

Add/verify tests around:

```text
- delegates OTP authorization to configured authorizer
- uses bound provider authorizer for Paynamics
- preserves optional metadata
- returns claim result consumed by ClaimApprovalOtpController
```

Important test:

```text
provider = paynamics
→ resolves Paynamics authorizer
→ passes OTP/reference_id/provider
→ returns normalized claim result
```

---

### Slice 2 — Normalize provider naming

The frontend and backend must agree on provider string.

Canonical provider:

```text
paynamics
```

If any test or frontend code uses:

```text
payanamics
```

then add normalization:

```php
'payanamics' => 'paynamics'
```

Prefer normalization in x-change support code, not scattered across Vue.

Suggested class:

```php
LBHurtado\XChange\Support\Claim\PayoutProviderNameNormalizer
```

or inline normalization inside `SubmitClaimApprovalOtp` if this is the only use.

---

### Slice 3 — Add/patch provider authorizer contract

If already present, reuse it.

Likely existing seam:

```php
ProviderClaimApprovalOtpAuthorizer
ClaimApprovalOtpAuthorizer
ClaimOtpVerificationContract
```

The contract should answer:

```text
Given voucher + OTP payload, can this provider authorize the pending payout?
```

Suggested generic shape:

```php
interface ProviderClaimApprovalOtpAuthorizer
{
    public function authorize(Voucher $voucher, array $payload): array|SubmitPayCodeClaimResultData;
}
```

Do not create this if an equivalent already exists.

Patch the existing one instead.

---

### Slice 4 — Implement concrete Paynamics adapter

Create or patch:

```php
LBHurtado\XChange\Support\Claim\PaynamicsClaimApprovalOtpAuthorizer
```

or, if provider-specific code belongs strictly in emi-paynamics:

```php
LBHurtado\EmiPaynamics\Claim\PaynamicsClaimApprovalOtpAuthorizer
```

Preferred split:

```text
x-change adapter
→ translates x-change voucher/claim payload into provider request

emi-paynamics client/action
→ performs actual Paynamics API call
```

The x-change adapter should call the concrete emi-paynamics action/client, not raw HTTP.

---

### Slice 5 — Bind Paynamics authorizer

Bind provider name to concrete authorizer.

Possible config:

```php
'claim_approval_otp' => [
    'default_provider' => env('XCHANGE_PAYOUT_PROVIDER', 'netbank'),

    'providers' => [
        'paynamics' => PaynamicsClaimApprovalOtpAuthorizer::class,
        'netbank' => NullClaimApprovalOtpAuthorizer::class,
    ],
],
```

Then `SubmitClaimApprovalOtp` resolves:

```text
payload.provider
or voucher/provider metadata
or XCHANGE_PAYOUT_PROVIDER
```

Resolution order:

```text
1. payload['provider']
2. voucher payout/provider metadata
3. config('x-change.claim_approval_otp.default_provider')
4. env('XCHANGE_PAYOUT_PROVIDER')
```

---

### Slice 6 — Patch existing controller only if needed

Existing controller should remain redirect/session based.

Patch only if needed to support frontend payload names.

Current accepted fields:

```php
'otp' => ['required', 'string'],
'reference_id' => ['nullable', 'string'],
'provider' => ['nullable', 'string'],
```

If frontend sends `otp_code`, either:

```text
- frontend should send otp
```

or backend validation should allow:

```php
'otp_code' => ['nullable', 'string']
```

But do not loosen validation unnecessarily if frontend already sends `otp`.

---

### Slice 7 — Frontend contract

Claim UI should submit:

```json
{
  "otp": "123456",
  "reference_id": "AUTH-123",
  "provider": "paynamics"
}
```

To:

```text
POST /x/claim/{code}/approval/otp
```

Expected server behavior:

```text
- redirects to success page if claim completes
- redirects back to approval page if still pending/fails
- stores compiled claim result in session
```

The web endpoint is not JSON-first.

If the UI needs JSON later, add a separate API route.

---

## 8. Regression-Limiting Tests

Before deeper changes, keep existing tests green:

```bash
./vendor/bin/pest tests/Unit/Actions/Claim/SubmitClaimApprovalOtpTest.php
./vendor/bin/pest tests/Feature/Claim/ClaimApprovalOtpControllerTest.php
```

Add or update focused tests:

```text
SubmitClaimApprovalOtp resolves paynamics provider
SubmitClaimApprovalOtp delegates to Paynamics authorizer
ClaimApprovalOtpController accepts frontend payload
ClaimApprovalOtpController stores compiled result
ClaimApprovalOtpController redirects correctly after provider authorization
```

Avoid broad lifecycle refactors until this path is solid.

---

## 9. Lifecycle Runner Update Later

The runner should eventually use the same adapter path:

```text
prompt OTP in CLI
→ call SubmitClaimApprovalOtp
→ display result
```

But this does not block claim UI integration.

The lifecycle runner is the live testing gold standard, but not the production endpoint.

---

## 10. What Not To Do Now

Do not add:

```text
new ClaimApprovalOtpController
new /x/claim/{code}/approval/otp route
PayoutAuthorization model
AuthorizePayout pipe
issuer approval queue
database notifications
Echo broadcasting
```

Those belong to the later Payout Authorization Gate phase.

For the current claim UI/UX work, the immediate job is:

```text
Make the existing web OTP approval endpoint invoke the correct Paynamics adapter.
```

---

## 11. Efficient Implementation Order

1. Inspect `SubmitClaimApprovalOtp`.
2. Inspect existing authorizer contract/binding.
3. Add/confirm provider-bound Paynamics authorizer test.
4. Normalize provider name.
5. Implement or patch Paynamics authorizer adapter.
6. Bind Paynamics adapter under provider key `paynamics`.
7. Run controller and frontend OTP tests.
8. Hand off route/payload contract to Claim UI/UX.

---

## 12. Final Mental Model

The Claim UI does not need new infrastructure.

It needs the existing endpoint to be wired correctly.

```text
Approval.vue
→ approvalOtpSubmitAdapter
→ POST /x/claim/{code}/approval/otp
→ ClaimApprovalOtpController
→ SubmitClaimApprovalOtp
→ Provider-bound authorizer
→ Paynamics OTP adapter
→ claim result redirect/session
```

This is now an adapter-wiring plan, not a broad refactor plan.
