# Paynamics Deferred OTP Lifecycle Intel

## Status

This report captures a temporary investigation performed while the Claim UX extraction was paused to establish a Paynamics OTP baseline.

The work should be treated as actionable refactor intelligence, not final architecture.

## Context

The host app is only the integration host. The main implementation lives in:

```text
packages/x-change
```

Recent commits already point the claim flow toward deferred Paynamics OTP handling:

```text
85438b1 feat: run web claims with deferred Paynamics OTP resolver
00d58df feat: route web claim submissions through deferred OTP flow
bfd0a38 feat: replay claim after approval OTP submission
d076257 test: replay claim through configured Paynamics OTP driver
0670748 test: replay Paynamics payout with submitted approval OTP
```

The activation plan in `packages/x-change/docs/todo/claim-ux-activation-plan.md` identifies the next UI/UX work as compiler-owned visible behavior, especially:

```text
C. Choose Approval / OTP UI
E. Render Compiled Form Directly
F. Final Live Trial Slice
```

The Paynamics investigation was started because lifecycle scenario runners were still capable of reaching an interactive OTP prompt, which blocks automation and makes live scenario validation unreliable.

## What Was Observed

The lifecycle scenario command:

```bash
php artisan xchange:lifecycle:run divisible_open_three_slices_enforced_interval --timeout=180 --poll=10
```

should be unattended automation.

The claim path underneath it can reach Paynamics payout approval. If the active `ConstellationOtpResolver` is interactive, the console lifecycle runner can prompt for OTP. That is the wrong ownership boundary:

```text
Lifecycle runner
    should observe and report approval state
    should not become an OTP input UI
```

Web claim submission already had this pattern:

```php
SubmitWebPayCodeClaim
    -> UseDeferredPaynamicsOtpResolver
    -> SubmitPayCodeClaim
```

The tactical patch applied the same behavior to lifecycle runners.

## Tactical Patch Summary

The temporary patch changed:

```text
packages/x-change/src/Lifecycle/Runners/DefaultClaimScenarioRunner.php
packages/x-change/src/Lifecycle/Runners/SequentialClaimsScenarioRunner.php
config/constellation.php
packages/x-change/tests/Feature/Console/LifecyclePaynamicsOtpResolverTest.php
```

The runner changes wrapped claim submission in:

```php
$this->deferredOtpResolver->run(
    fn () => $this->submitPayCodeClaim->handle(...)
);
```

The host config change added the missing published resolver alias:

```php
'deferred' => DeferredOtpResolver::class,
```

The test asserted that both default and sequential lifecycle claims submit under `DeferredOtpResolver`.

## Verification Performed

The focused lifecycle/deferred OTP tests passed:

```bash
./vendor/bin/pest --compact tests/Feature/Console/LifecyclePaynamicsOtpResolverTest.php
```

Additional adjacent tests passed:

```bash
./vendor/bin/pest --compact \
  tests/Feature/Console/LifecyclePaynamicsOtpResolverTest.php \
  tests/Unit/Support/Claim/UseDeferredPaynamicsOtpResolverTest.php \
  tests/Unit/Actions/Redemption/SubmitWebPayCodeClaimTest.php \
  tests/Feature/Claim/PaynamicsApprovalOtpReplayAdapterTest.php \
  tests/Feature/Console/LifecycleScenarioEngineTest.php \
  tests/Feature/Console/LifecycleSequentialClaimsScenarioRunnerTest.php
```

Result:

```text
11 passed, 37 assertions
```

## Live Paynamics Baseline

The configured Paynamics credentials are valid for read/probe operations.

This command succeeded:

```bash
php artisan constellation:probe --no-interaction
```

Observed result:

```text
Consumer: DEFAULT_CONSUMER
Merchant: DEFAULT_MERCHANT
Supported Banks: 141
API credentials verified successfully.
```

Wallet lookups also succeeded for:

```text
CNSTWLLT9GSPQ1
CNSTWLLT5BPIUG
```

However, live OTP requests to:

```text
POST /v1/integration/corp_wallet/request_otp
```

failed with:

```json
{
  "response_code": "GR028",
  "response_message": "Action Not Allowed.",
  "response_advise": "The authentication used in sending request is not allowed to do this type of transaction."
}
```

This happened for both:

```text
CNSTWLLT9GSPQ1 -> CNSTCUSTTVR6B3
CNSTWLLT5BPIUG -> CNSTMRCHRKN6T4
```

The amount was tested at both `25.00` and `50.00`. The same `GR028` response remained after correcting the manual signature and bank mapping.

No live withdrawal was submitted because OTP issuance failed.

## Postman Collection Notes

The relevant Postman collection was:

```text
~/Documents/Paynamics/Share - Constellation v1.42.postman_collection 3.json
```

The collection confirms the OTP request shape:

```text
account_id
bank_id
request_id
bank_account_no
amount
reason
signature
```

The OTP signature formula is:

```text
account_id + bank_account_no + request_id + reason + amount + integration_key
```

The current package implementation signs with:

```php
config('constellation.merchant_key')
```

and matches the observed Postman formula.

The non-registered cash-out request in the collection accepts an optional `fee`, but the current blocker occurs before cash-out submission, at OTP request time.

## Interpretation

There are two separate concerns:

```text
1. Local lifecycle OTP prompt behavior
2. Paynamics GR028 authorization failure
```

The tactical code patch addressed concern 1.

It did not cause concern 2. The `GR028` response comes directly from Paynamics when calling the live OTP endpoint with credentials that still pass probe/read checks.

The likely Paynamics-side issue is one of:

```text
- transactional cash-out OTP permission disabled
- request_otp action not enabled for the API user
- withdraw_not_registered product not enabled
- merchant/account permission matrix changed
- UAT/sandbox configuration reset
```

Paynamics Support is the correct next dependency for this specific live failure.

## Refactor Guidance

Do not let lifecycle runners become provider-specific OTP UI surfaces.

The behavioral invariant should be:

```text
Any automated scenario runner must use non-interactive claim submission.
```

The current tactical implementation directly injects:

```php
UseDeferredPaynamicsOtpResolver
```

into lifecycle runners.

That is acceptable as a short-term bug fix, but it is probably not the final architecture for the Claim UX extraction.

The extraction should move this decision behind a clearer orchestration boundary, for example:

```text
LifecycleClaimSubmitter
ClaimSubmissionRuntime
ClaimApprovalOrchestrator
ClaimExecutionContext
```

The new boundary should accept an execution mode such as:

```text
interactive web claim
deferred web claim
automated lifecycle claim
test/fake claim
```

and choose the appropriate OTP/approval policy internally.

## Recommended Agent Instructions

1. Preserve the behavior proven by the tactical patch:

```text
Lifecycle scenario runners must not prompt for Paynamics OTP.
```

2. Do not hard-code Paynamics-specific resolver details deeper into lifecycle runners if the extraction creates a claim orchestration layer.

3. Prefer moving the deferred OTP choice to the same layer that owns claim submission and approval initiation.

4. Keep a test equivalent to:

```text
Default lifecycle runner submits under DeferredOtpResolver
Sequential lifecycle runner submits under DeferredOtpResolver
```

but rewrite it against the new orchestration boundary if that boundary exists.

5. Keep `config/constellation.php` aligned with the package config so this alias remains available:

```php
'deferred' => DeferredOtpResolver::class,
```

6. Treat Paynamics `GR028` as external integration state until Paynamics Support confirms otherwise.

7. Do not attempt to solve `GR028` by changing Claim UX compiler, form-flow rendering, redirect ownership, or approval UI code.

8. Once Paynamics Support resolves permissions, rerun:

```bash
php artisan xchange:lifecycle:run divisible_open_three_slices_enforced_interval --timeout=180 --poll=10
```

Expected result after Paynamics permission is fixed:

```text
- no local OTP prompt
- claim creates pending approval or proceeds according to provider response
- lifecycle runner reports provider status instead of blocking for OTP input
```

## Recommendation On Current Tactical Patch

Do not commit the tactical patch as final architecture while the Claim UX extraction is active.

Recommended handling:

```text
stash the tactical source/test patch
keep this report committed or available to the extraction agent
let the extraction thread implement the same behavior at the new boundary
```

Reason:

```text
The patch is behaviorally correct, but it may create merge noise if the other thread is already extracting the deferred OTP resolver or claim submission boundary.
```

The report is the important artifact. The code patch is recoverable implementation evidence.
