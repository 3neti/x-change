# Efficient Refactor Strategy — Paynamics Payout OTP Approval Flow

## Objective

Refactor the existing Paynamics OTP handling from the Lifecycle Scenario Runner into reusable x-change application actions/endpoints, so both:

```text
Lifecycle Scenario Runner
Claim UI/UX
Issuer Approval UI
API clients
```

can consume the same underlying approval flow.

The baseline is the existing Lifecycle Scenario Runner, which is already running and tested. Therefore, the plan should avoid rebuilding the flow from scratch.

---

## 1. Guiding Principle

Do **not** create a parallel Payout OTP system yet.

Use the existing approval workflow as the seed.

The current system already appears to have these useful seams:

```text
SubmitPayCodeClaim
DefaultApprovalWorkflowService
DefaultClaimApprovalInitiationService
DefaultClaimApprovalExecutionService
ClaimOtpChallengeContract
ClaimOtpVerificationContract
VerifyVoucherClaimOtpController
ApproveVoucherClaimController
ClaimApprovalOtpController
```

The goal is to promote the current Lifecycle Scenario Runner OTP behavior into reusable application-layer actions/services.

The runner should become a client of the same flow used by the web/API controllers.

---

## 2. Target Architecture

Current risk:

```text
RunLifecycleScenarioCommand
  → contains Paynamics OTP orchestration
```

Target:

```text
Application Actions / Services
  → contain Paynamics OTP approval orchestration

RunLifecycleScenarioCommand
  → calls application actions/services

Web Controllers
  → call application actions/services

API Controllers
  → call application actions/services

Claim UI/UX
  → calls web/API endpoints
```

The claim UI must not call the Lifecycle Scenario Runner.

The controllers must not call the Lifecycle Scenario Runner.

The Lifecycle Scenario Runner should be refactored to use the same application actions/controllers that the UI uses.

---

## 3. Scope of This Refactor

This plan focuses on **extraction and endpoint exposure**, not the full future PayoutAuthorization model yet.

### In scope

```text
- Characterization tests for current Paynamics OTP behavior
- Extract runner OTP logic into reusable actions/services
- Add local web/API endpoints for approval OTP
- Refactor lifecycle runner to call extracted actions/services
- Keep current approval workflow contracts
- Preserve existing behavior
- Prepare for future persistent PayoutAuthorization model
```

### Out of scope for this slice

```text
- New PayoutAuthorization database model
- Full issuer payout authorization dashboard
- Laravel Echo broadcasting
- Webhook feedback strategy
- Full AuthorizePayout pipeline integration
- Multiple-voucher approval queue UI
```

Those can follow once the reusable OTP approval surface is stable.

---

## 4. Regression-Limiting Strategy

Before changing behavior, lock the existing behavior with characterization tests.

Do not refactor first.

The efficient sequence is:

```text
1. Characterize current working behavior
2. Extract logic
3. Refactor runner to use extracted logic
4. Add endpoints
5. Confirm old tests still pass
6. Add only minimal new tests for endpoint parity
```

---

## 5. Existing Tests to Lean On

Use the current test coverage around:

```text
Unit/Services/ClaimApprovalInitiationServiceTest.php
Unit/Services/ClaimApprovalExecutionServiceTest.php
Unit/Actions/Claim/SubmitClaimApprovalOtpTest.php
Feature/Api/Lifecycle/Claims/VerifyVoucherClaimOtpLifecycleRouteTest.php
Feature/Claim/ClaimApprovalOtpControllerTest.php
frontend/approvalOtpSubmission.test.ts
```

These likely already cover:

```text
- approval_required result from SubmitPayCodeClaim
- workflow initiation when requirements include otp
- OTP challenge request delegation
- OTP verification delegation
- claim replay with approval.resume = true
- workflow clearing after success
- workflow preservation after replay failure
- web/lifecycle OTP endpoint behavior
- frontend OTP submission payload
```

---

## 6. Missing Characterization Tests to Add First

Add only tests that protect the current Paynamics OTP path before extraction.

### Test 1 — Paynamics payout returns approval requirement

Goal:

```text
When payout provider is Paynamics and claim submit requires OTP,
SubmitPayCodeClaim returns approval_required with otp requirement.
```

Assert:

```text
status = approval_required
approval_requirements contains otp
approval_metadata contains provider/paynamics context if currently available
```

### Test 2 — OTP challenge request is delegated

Goal:

```text
When approval workflow starts,
ClaimOtpChallengeContract::request() is called.
```

Assert:

```text
OTP challenge contract receives voucher/reference/user context
workflow is stored
response includes challenge/reference metadata
```

### Test 3 — OTP verification resumes claim

Goal:

```text
When OTP is verified successfully,
DefaultClaimApprovalExecutionService replays SubmitPayCodeClaim with approval.resume = true.
```

Assert:

```text
ClaimOtpVerificationContract::verify() is called
SubmitPayCodeClaim::handle() is called again
payload includes approval.resume = true
workflow clears on success
```

### Test 4 — Failed replay keeps workflow

Goal:

```text
If OTP verifies but replay fails,
workflow remains pending.
```

Assert:

```text
workflow store still has pending workflow
failure result is returned
```

### Test 5 — Web and lifecycle endpoints are equivalent

Goal:

```text
Claim UI endpoint and lifecycle endpoint should produce equivalent OTP verification behavior.
```

Assert:

```text
same action/service is called
same result shape or compatible result shape is returned
```

---

## 7. Extraction Targets

Inspect `RunLifecycleScenarioCommand.php` and identify any direct logic for:

```text
- determining Paynamics provider
- prompting for OTP
- requesting OTP
- verifying OTP
- resubmitting claim
- resuming payout
- formatting approval_required result
```

Move the reusable parts into actions/services.

The runner may keep only:

```text
- CLI prompt
- console output
- scenario progress logging
```

The runner should not own provider approval business logic.

---

## 8. Proposed Application Actions / Services

Prefer reusing existing classes first.

If current services are enough, do not create new ones.

### Existing services to reuse

```php
DefaultClaimApprovalInitiationService
DefaultClaimApprovalExecutionService
DefaultApprovalWorkflowService
```

### If needed, add thin action wrappers

```php
LBHurtado\XChange\Actions\Claim\InitiateClaimApproval
LBHurtado\XChange\Actions\Claim\SubmitClaimApprovalOtp
```

These should wrap existing services rather than duplicate logic.

### `InitiateClaimApproval`

Responsibility:

```text
- accept voucher/code and approval_required result
- start approval workflow
- request OTP challenge through ClaimOtpChallengeContract
- return workflow/challenge payload
```

### `SubmitClaimApprovalOtp`

Responsibility:

```text
- accept voucher/code/reference_id/otp
- verify OTP through ClaimOtpVerificationContract
- replay claim using approval.resume = true
- return normalized result
```

If `SubmitClaimApprovalOtp` already exists, extend it instead of creating another class.

---

## 9. Endpoint Strategy

Create local x-change endpoints that the Claim UI/UX can call.

Do not make the UI depend on lifecycle routes if those are demo/test-specific.

### Web endpoints

```text
POST /x/claim/{code}/approval
POST /x/claim/{code}/approval/otp
```

Possible controllers:

```php
LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimApprovalController
LBHurtado\XChange\Http\Controllers\Web\Claim\ClaimApprovalOtpController
```

### API endpoints

```text
POST /api/x/v1/claims/{code}/approval
POST /api/x/v1/claims/{code}/approval/otp
```

Possible controllers:

```php
LBHurtado\XChange\Http\Controllers\Api\Claim\InitiateClaimApprovalController
LBHurtado\XChange\Http\Controllers\Api\Claim\VerifyClaimApprovalOtpController
```

### Initial payload for OTP submission

```json
{
  "reference_id": "approval-reference",
  "otp": "123456"
}
```

or if current code uses a different key:

```json
{
  "reference_id": "approval-reference",
  "otp_code": "123456"
}
```

Preserve existing key names where possible to avoid frontend churn.

---

## 10. Claim UI Contract

When claim submit returns approval requirement, the Claim UI should not know Paynamics directly.

Expected claim submit response:

```json
{
  "success": false,
  "status": "approval_required",
  "approval": {
    "requirements": ["otp"],
    "reference_id": "...",
    "message": "Payout approval required."
  }
}
```

The UI should then call:

```text
POST /x/claim/{code}/approval/otp
```

with:

```json
{
  "reference_id": "...",
  "otp": "123456"
}
```

The UI should show:

```text
This payout requires issuer approval.
Enter the Payout OTP sent to the issuer's registered mobile number.
```

For now, this can be simple. The full issuer dashboard can come later.

---

## 11. Lifecycle Runner Refactor

After endpoints/actions exist, refactor the runner.

### Before

```text
Runner directly handles Paynamics OTP logic
```

### After

```text
Runner detects approval_required
→ displays approval message
→ prompts OTP in CLI
→ calls SubmitClaimApprovalOtp action/service
→ prints result
```

The runner remains interactive, but it uses the same application action as the UI.

This preserves the runner as the gold-standard live simulator while removing business logic from the command.

---

## 12. What Not to Do Yet

Do not introduce the following in this slice unless the existing cache workflow is clearly insufficient:

```text
PayoutAuthorization model
xchange_payout_authorizations table
issuer approval queue dashboard
database notification
AuthorizePayout pipe integration
Echo/broadcast scaffolding
```

Reason:

The immediate problem is to avoid duplicating Paynamics OTP logic between:

```text
Lifecycle runner
Claim UI/UX
future API
```

The existing approval workflow may already solve much of this. Add persistence only after we confirm the existing workflow store cannot serve the next UI requirement.

---

## 13. Efficient Implementation Order

### Slice A — Characterization tests

Add tests around the current Paynamics OTP approval behavior.

Run:

```bash
./vendor/bin/pest tests/Unit/Services/ClaimApprovalInitiationServiceTest.php
./vendor/bin/pest tests/Unit/Services/ClaimApprovalExecutionServiceTest.php
./vendor/bin/pest tests/Unit/Actions/Claim/SubmitClaimApprovalOtpTest.php
./vendor/bin/pest tests/Feature/Api/Lifecycle/Claims/VerifyVoucherClaimOtpLifecycleRouteTest.php
./vendor/bin/pest tests/Feature/Claim/ClaimApprovalOtpControllerTest.php
```

Then add only missing tests.

### Slice B — Extract action/service layer

Extract reusable OTP initiation and verification/resume logic from runner into:

```php
SubmitClaimApprovalOtp
```

and, if needed:

```php
InitiateClaimApproval
```

Prefer extending existing classes over creating new ones.

### Slice C — Add local web/API endpoints

Expose the extracted actions through x-change controllers.

Use thin controllers.

No business logic in controllers.

### Slice D — Refactor lifecycle runner

Update `RunLifecycleScenarioCommand.php` to call the same actions/services.

Keep CLI prompt only.

### Slice E — Endpoint parity tests

Add tests proving:

```text
lifecycle OTP route
web claim OTP route
API claim OTP route
```

all use the same underlying action/service and produce compatible results.

### Slice F — Claim UI handoff

Once endpoints are stable, hand off to the Claim UI/UX thread.

Provide:

```text
routes
payloads
expected responses
approval_required state handling
OTP submission flow
```

---

## 14. Future Follow-Up After This Refactor

Once the extracted approval flow is stable, then proceed to the full Payout Authorization Gate work:

```text
AuthorizePayout pipe
PayoutAuthorization model/table
issuer approval queue
database notifications
multiple-voucher approval management
Paynamics provider challenge metadata
resume/retry hardening
```

That future work will be safer because the approval actions/endpoints will already be shared and tested.

---

## 15. Final Recommendation

The most efficient path is:

```text
Do not replace the existing approval workflow.
Promote it.

Do not make the UI use the Lifecycle Runner.
Make both use the same extracted actions/endpoints.

Do not add persistent PayoutAuthorization yet.
First confirm whether the current workflow store can be upgraded or adapted.

Do not introduce AuthorizePayout yet.
First stabilize the OTP approval application surface.
```

The immediate goal is to turn the current Lifecycle Scenario Runner Paynamics OTP path into a reusable x-change approval API that the Claim UI/UX can consume.
