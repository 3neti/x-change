# Updated OTP Authorization Adapter Plan — Corrected Around Paynamics `ConstellationOtpResolver`

## 1. Current Correction

We discovered that the working Paynamics OTP logic is already inside `emi-paynamics`.

The Lifecycle Scenario Runner works because payout execution eventually reaches:

```text
ConstellationPayoutProvider
→ ConstellationOtpResolver::resolve()
→ InteractiveOtpResolver
→ CreateCashOutOtp::handle()
→ CLI prompts for OTP
→ CreateCashOutNonRegistered::handle(... otp ...)
```

Therefore, the real Paynamics OTP seam is:

```php
LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver
```

Not:

```php
LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract
```

The `WithdrawalOtpApprovalServiceContract` path is useful for x-change claim approval mechanics, but it is not the actual working Paynamics cash-out OTP engine.

---

## 2. What We Should Stop Doing

Stop the `PaynamicsWithdrawalOtpApprovalService` path for now.

Do not scaffold or continue:

```php
LBHurtado\XChange\Services\PaynamicsWithdrawalOtpApprovalService
```

unless we later decide to bridge it to `ConstellationOtpResolver`.

Also pause this env/config route:

```env
XCHANGE_WITHDRAWAL_OTP_DRIVER=paynamics
```

because it would create a second Paynamics OTP pathway, separate from the working `emi-paynamics` pathway.

---

## 3. Source Codes That May Need Deletion or Reversal Later

These are not necessarily wrong, but they may be unnecessary if the final architecture uses `ConstellationOtpResolver` directly.

### Do not add / delete if already scaffolded but uncommitted

```text
src/Services/PaynamicsWithdrawalOtpApprovalService.php
tests/Unit/Services/PaynamicsWithdrawalOtpApprovalServiceTest.php
```

### Revert if already patched but uncommitted

```text
config/x-change.php
```

Specifically remove any added node like:

```php
'withdrawal' => [
    'otp' => [
        'paynamics' => [
            'service' => PaynamicsWithdrawalOtpApprovalService::class,
        ],
    ],
],
```

### Revert if already patched but uncommitted

```text
src/Providers/XChangeServiceProvider.php
```

Specifically remove any added match branch like:

```php
'paynamics' => $app->make(PaynamicsWithdrawalOtpApprovalService::class),
```

### Keep for now

```text
src/Actions/Claim/SubmitClaimApprovalOtp.php
src/Support/Claim/ClaimApprovalOtpAuthorizerResolver.php
src/Support/Claim/ClaimApprovalProviderNormalizer.php
src/Support/Claim/ConfiguredClaimApprovalOtpAuthorizer.php
tests/Unit/Support/Claim/ConfiguredClaimApprovalOtpAuthorizerTest.php
tests/Unit/Actions/Claim/SubmitClaimApprovalOtpConfiguredDriverTest.php
tests/Unit/Services/WithdrawalOtpApprovalBackedClaimOtpChallengeServiceTest.php
tests/Unit/Services/WithdrawalOtpApprovalBackedClaimOtpVerificationServiceTest.php
tests/Unit/Contracts/WithdrawalOtpApprovalServiceContractTest.php
```

These helped characterize the current x-change approval pathway and are not harmful.

But treat them as supporting scaffolds, not the final Paynamics integration seam.

---

## 4. Correct Target Architecture

The correct target is:

```text
Claim UI
→ existing ClaimApprovalOtpController
→ SubmitClaimApprovalOtp
→ stores/validates submitted OTP in x-change workflow
→ Paynamics payout resumes through ConstellationPayoutProvider
→ ConstellationOtpResolver supplies OTP without CLI prompt
→ CreateCashOutNonRegistered submits Paynamics cash-out with OTP
```

The key shift:

```text
Do not duplicate Paynamics OTP request/cash-out logic in x-change.

Instead, make the existing emi-paynamics ConstellationOtpResolver support web/deferred OTP resolution.
```

---

## 5. Package Responsibility

## emi-paynamics

Owns:

```text
- request Paynamics cash-out OTP
- cash-out with OTP
- ConstellationPayoutProvider flow
- ConstellationOtpResolver contract
```

Current working CLI implementation:

```php
InteractiveOtpResolver
```

Future web implementation:

```php
DeferredConstellationOtpResolver
```

or:

```php
WorkflowBackedConstellationOtpResolver
```

## x-change

Owns:

```text
- claim approval page
- OTP submission endpoint
- approval workflow/session/cache
- storing submitted OTP for the pending claim
- telling emi-paynamics resolver where to get the OTP
```

## lifecycle runner

Owns:

```text
- CLI simulation
- can continue using InteractiveOtpResolver
- later may optionally use the same workflow-backed resolver for parity
```

---

## 6. Updated Implementation Plan

## Slice 1 — Characterize `ConstellationOtpResolver`

Add tests in `emi-paynamics` proving:

```text
InteractiveOtpResolver
→ calls CreateCashOutOtp
→ obtains OTP via callback/STDIN
→ returns OTP string
```

Classes to inspect/test:

```text
packages/emi-paynamics/src/Contracts/ConstellationOtpResolver.php
packages/emi-paynamics/src/Support/InteractiveOtpResolver.php
packages/emi-paynamics/src/Support/NullOtpResolver.php
packages/emi-paynamics/src/Adapters/ConstellationPayoutProvider.php
```

Goal:

```text
Lock current working behavior before adding web resolver.
```

---

## Slice 2 — Add a web/deferred OTP resolver in emi-paynamics

Create:

```php
LBHurtado\EmiPaynamicsConstellation\Support\DeferredOtpResolver
```

Possible behavior:

```text
- request OTP through CreateCashOutOtp
- instead of prompting on STDIN, throw/return a structured pending OTP signal
- include request payload, provider, amount, reference, target account
```

Possible control signal:

```php
PendingConstellationOtpException
```

or result object if the existing interface can support it.

Important problem:

The current `ConstellationOtpResolver::resolve()` returns `string`.

So a deferred resolver cannot naturally return “pending” unless it:

```text
A. throws a domain exception
B. blocks until OTP appears in a store
C. returns a configured/test OTP
```

For web UI, the cleanest option is probably:

```text
throw PendingConstellationOtpException
```

Then x-change catches it and routes user/issuer to approval UI.

---

## Slice 3 — Add x-change workflow-backed OTP store

x-change needs a way to store:

```text
voucher_code
reference_id
provider
otp request payload
submitted OTP
status
expires_at
```

This can initially reuse the existing claim approval workflow store if possible.

Avoid adding a new database table unless the existing store is insufficient.

Potential integration point:

```text
ClaimApprovalWorkflowStoreContract
```

Goal:

```text
When Paynamics resolver requests OTP in web context, x-change records pending approval metadata.
When user submits OTP to /x/claim/{code}/approval/otp, x-change stores the OTP against that workflow/reference.
```

---

## Slice 4 — Add x-change-backed resolver for emi-paynamics

Create an implementation of:

```php
ConstellationOtpResolver
```

that is owned by x-change or supplied by x-change to the container.

Possible name:

```php
LBHurtado\XChange\Support\Paynamics\ClaimApprovalBackedConstellationOtpResolver
```

Behavior:

```text
resolve($otpRequestPayload)
→ if submitted OTP exists for current workflow/reference:
      return submitted OTP
→ else:
      request OTP if not yet requested
      store pending approval metadata
      throw PendingConstellationOtpException
```

This lets `ConstellationPayoutProvider` remain unchanged.

---

## Slice 5 — Catch pending OTP during claim submit

When payout execution triggers Paynamics OTP and the resolver throws pending OTP:

```text
ClaimSubmitController / SubmitPayCodeClaim path
→ catch pending OTP signal
→ compile claim result with status approval_required
→ redirect to claim approval page
```

The approval page already exists.

The existing endpoint remains:

```text
POST /x/claim/{code}/approval/otp
```

---

## Slice 6 — Resume payout after OTP submission

When the approval OTP endpoint receives the OTP:

```text
ClaimApprovalOtpController
→ SubmitClaimApprovalOtp
→ store submitted OTP in workflow
→ replay/resume claim
→ ConstellationOtpResolver now returns OTP
→ ConstellationPayoutProvider submits cash-out
→ success/redirect
```

This is the actual web equivalent of the working lifecycle runner.

---

## Slice 7 — Lifecycle runner parity

Keep the runner working.

Options:

```text
Option A:
Lifecycle runner keeps InteractiveOtpResolver.

Option B:
Lifecycle runner uses the workflow-backed resolver and submits OTP through SubmitClaimApprovalOtp.

Start with Option A.
```

Do not break the working CLI flow.

---

## 7. Updated Mental Model

Old mistaken model:

```text
x-change should create a PaynamicsWithdrawalOtpApprovalService
→ Paynamics OTP verification happens through x-change withdrawal OTP driver
```

Corrected model:

```text
emi-paynamics already owns the Paynamics OTP/cash-out sequence.

x-change should provide a web-safe OTP resolver/workflow so emi-paynamics can obtain the OTP without CLI input.
```

---

## 8. Updated Flow

### CLI / Lifecycle Runner Today

```text
Lifecycle Runner
→ claim submit
→ payout provider: ConstellationPayoutProvider
→ InteractiveOtpResolver
→ request OTP
→ prompt terminal
→ submit cash-out with OTP
→ success
```

### Web Claim UI Target

```text
Claim UI
→ claim submit
→ payout provider: ConstellationPayoutProvider
→ x-change-backed ConstellationOtpResolver
→ request OTP
→ store pending approval
→ redirect/show approval page
→ issuer enters OTP
→ SubmitClaimApprovalOtp
→ store submitted OTP
→ replay claim
→ x-change-backed ConstellationOtpResolver returns OTP
→ submit cash-out with OTP
→ success
```

---

## 9. Final Recommendation

Do not continue the `PaynamicsWithdrawalOtpApprovalService` branch.

The next real implementation slice should be:

```text
Characterize emi-paynamics ConstellationOtpResolver and ConstellationPayoutProvider OTP flow.
```

Then add:

```text
x-change-backed ConstellationOtpResolver
```

as the bridge between the existing claim approval UI and the existing Paynamics payout provider.
