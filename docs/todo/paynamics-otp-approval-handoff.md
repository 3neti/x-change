# Paynamics OTP Approval Handoff

## Status

The Paynamics OTP approval backend is now wired as a shared approval pipeline used by both the Claim UI flow and the Lifecycle Scenario Runner.

## Completed Backend Guarantees

### Claim submission may pause

`SubmitPayCodeClaim` may return `ClaimApprovalInitiationResultData` with:

- `status = approval_required`
- `requirements = ['otp']`
- `meta.provider = paynamics`
- `meta.authorization_type = otp`
- `meta.reference_id = <Paynamics request id>`

### Web claim flow supports replay

The web flow now stores the original claim payload when approval is required.

`POST /x/claim/{code}/approval/otp`:

1. submits OTP through `SubmitClaimApprovalOtp`
2. stores the submitted OTP via the configured Paynamics withdrawal OTP driver
3. rebuilds the replay payload using `ClaimApprovalResumePayload`
4. replays the claim through `SubmitWebPayCodeClaim`
5. redirects to success when replay completes

### Lifecycle runner supports approval completion

Lifecycle runners now submit through `LifecycleClaimSubmitter`.

They can detect approval-required claim results through `LifecycleApprovalRequiredResult`.

Interactive console lifecycle runs can complete approval through `LifecycleApprovalOtpCompleter`.

The completion flow is:

```text
approval_required
→ prompt OTP
→ SubmitClaimApprovalOtp
→ ClaimApprovalResumePayload
→ SubmitWebPayCodeClaim
→ poll disbursement
```

### **Paynamics OTP handling**

PaynamicsWithdrawalOtpApprovalService::verify() does not perform a standalone Paynamics OTP verification call.

Instead, it stores the submitted OTP by Paynamics request id.

The actual provider verification happens when the replayed Paynamics cash-out request submits the OTP to Constellation.

**Important Classes**
---------------------

*   SubmitPayCodeClaim
*   SubmitWebPayCodeClaim
*   SubmitClaimApprovalOtp
*   ClaimApprovalResumePayload
*   ClaimApprovalResumePayloadSession
*   ClaimApprovalPendingOtpStore
*   PaynamicsWithdrawalOtpApprovalService
*   LifecycleClaimSubmitter
*   LifecycleApprovalRequiredResult
*   LifecycleApprovalOtpCompleter

**UI/UX Boundary**
------------------

The Claim UI/UX thread should consume the backend contract.

It should not create another Paynamics OTP implementation.

The UI owns:

*   approval screen layout
*   OTP input
*   submit UX
*   validation messages
*   visual states
*   success rendering

The backend owns:

*   approval result shape
*   OTP submission handling
*   replay payload
*   claim replay
*   provider OTP storage
*   lifecycle runner completion

**Known External Issue**
------------------------

Live Paynamics OTP request may fail with:

```text
GR028 Action Not Allowed
```

This is treated as Paynamics-side credential/product permission state, not a Claim UI or x-change orchestration bug.

Do not attempt to fix GR028 in UI code.

**Recommended Validation**
--------------------------
```bash
./vendor/bin/pest tests/Unit/Services/PaynamicsWithdrawalOtpApprovalServiceTest.php
./vendor/bin/pest tests/Feature/Claim/ClaimApprovalOtpControllerConfiguredDriverReplayTest.php
./vendor/bin/pest tests/Feature/Claim/PaynamicsApprovalOtpReplayAdapterTest.php
./vendor/bin/pest tests/Feature/Claim/ClaimApprovalOtpControllerTest.php
./vendor/bin/pest tests/Feature/Console/LifecycleScenarioEngineTest.php
./vendor/bin/pest tests/Feature/Console/LifecycleSequentialClaimsScenarioRunnerTest.php
```

Then perform live validation once Paynamics permissions are fixed:
```bash
php artisan xchange:lifecycle:run basic\_cash --provider=paynamics --timeout=180 --poll=10
```

Expected interactive behavior:
```text
Approval required.
Provider: paynamics
Reference:
Enter approval OTP:
Polling disbursement status...
...
Attempt \[default\]: SUCCEEDED as expected
```
___
