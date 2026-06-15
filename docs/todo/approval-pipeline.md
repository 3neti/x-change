# Task: Finish `--approval-pipeline` Lifecycle OTP Flow for Paynamics

We are in `packages/x-change`.

## Goal

Make this command:

```bash
php artisan xchange:lifecycle:run basic_cash --approval-pipeline --timeout=30 --poll=5
```

exercise the shared approval pipeline instead of Constellation's inline interactive OTP resolver.

Expected UX:

```text
Running scenario: basic_cash
Estimating cost...
Generating voucher...
Attempt [default] using mobile 639171234567...

Approval required.
Provider: paynamics
Reference: TEST-XXXX-09171234567
Enter approval OTP:
```

It must **not** show:

```text
[Paynamics OTP] OTP successfully sent...
Enter OTP:
```

That old output means the provider's inline `InteractiveOtpResolver` path is being used.

## Current Status

We added:

- `--approval-pipeline` option to `RunLifecycleScenarioCommand`
- `LifecycleScenarioRunOptions::$approvalPipeline`
- `_runtime.approval_pipeline`
- `ScenarioRunContext::usesApprovalPipeline()`
- `LifecycleClaimSubmitter::shouldDeferApproval()` now returns true for JSON or approval-pipeline mode
- `UseDeferredPaynamicsOtpResolver` now attempts to swap `ConstellationOtpResolver` to `DeferredOtpResolver`
- `LifecycleApprovalOtpCompleter` exists and should prompt using:
    - `Approval required.`
    - `Provider: paynamics`
    - `Reference: ...`
    - `Enter approval OTP`

Tests currently pass around the resolver and submitter, but the live run still does not invoke `LifecycleApprovalOtpCompleter`.

## Live Observation

With:

```bash
php artisan xchange:lifecycle:run basic_cash --approval-pipeline --timeout=30 --poll=5
```

we receive a real OTP on the phone, but there is no approval-pipeline prompt. The runner immediately polls:

```text
Attempt [default] using mobile 639171234567...
Polling disbursement status...
[poll 1/6 | 0s] status=unknown provider_tx=n/a needs_review=yes
...
```

Laravel / Constellation logs show:

```text
[LifecycleClaimSubmitter] should_defer=true
[UseDeferredPaynamicsOtpResolver] Activated
[ConstellationPayoutProvider] requesting OTP
[DisburseCash] Disbursement failed — recording pending status
error="Paynamics payout OTP is pending."
[RedeemVoucher] Redemption succeeded
```

Meaning:

```text
The deferred resolver suppresses inline prompting,
but the voucher redemption pipeline swallows the pending OTP exception
and returns a normal redeemed/claim-submitted result.
```

Therefore the runner never sees `ClaimApprovalInitiationResultData`, so `LifecycleApprovalOtpCompleter` is never invoked.

## Important Current Diff / Working Theory

There is a current draft diff that tries to fix this in `SubmitPayCodeClaim` by detecting pending Paynamics OTP after normalization. It adds:

```php
use LBHurtado\XChange\Support\Claim\ClaimApprovalPendingOtpStore;
```

and post-normalization checks:

```php
if (! $this->isApprovalReplay($payload)) {
    $pendingOtp = $this->pendingPaynamicsOtpMetadata($voucher, $normalized, $payload);

    if ($pendingOtp !== null) {
        return $this->toPendingPaynamicsOtpApprovalResult($voucher, $normalized, $pendingOtp);
    }
}

if (
    ! $this->isApprovalReplay($payload)
    && data_get($payload, 'approval.pipeline') === true
    && data_get($payload, 'approval.provider') === 'paynamics'
    && $this->isDeferredPaynamicsOtpPendingResult($normalized)
) {
    return $this->toDeferredPaynamicsOtpApprovalResult($voucher, $normalized, $payload);
}
```

But this still did not trigger in live testing.

Do **not** blindly commit that debug-heavy diff. Make it deterministic and tested.

## Immediate Task

1. Add temporary diagnostic logging right after `SubmitPayCodeClaim` normalizes the result:

```php
$normalized = $this->normalizeResult($voucher, $result, $payload);

\Illuminate\Support\Facades\Log::info('[SubmitPayCodeClaim] normalized result after deferred Paynamics claim', [
    'voucher_code' => (string) $voucher->code,
    'approval_pipeline' => data_get($payload, 'approval.pipeline'),
    'approval_provider' => data_get($payload, 'approval.provider'),
    'claim_type' => $normalized->claim_type,
    'status' => $normalized->status,
    'messages' => $normalized->messages,
    'disbursement' => $normalized->disbursement,
    'payload_keys' => array_keys($payload),
    'bank_account' => data_get($payload, 'bank_account'),
]);
```

2. Run:

```bash
php artisan xchange:lifecycle:run basic_cash --approval-pipeline --timeout=10 --poll=5
```

3. Inspect the log output for the exact normalized shape.

We need to know:

- Is `approval.pipeline` actually present in the payload passed to `SubmitPayCodeClaim`?
- Is `approval.provider` actually `paynamics`?
- Is `claim_type` really `redeem`?
- Is `status` really `redeemed`?
- What exactly is in `$normalized->messages`?
- What exactly is in `$normalized->disbursement`?
- Is `bank_account.account_number` available in the payload?

## Expected Final Fix Shape

Once the normalized shape is known, implement a conservative detector in `SubmitPayCodeClaim`:

```php
$normalized = $this->normalizeResult($voucher, $result, $payload);

if (
    ! $this->isApprovalReplay($payload)
    && $this->isApprovalPipelinePaynamicsPayload($payload)
    && $this->isDeferredPaynamicsOtpPendingResult($normalized, $payload)
) {
    return $this->toDeferredPaynamicsOtpApprovalResult($voucher, $normalized, $payload);
}
```

This should return:

```php
ClaimApprovalInitiationResultData::from([
    'status' => 'approval_required',
    'voucher_code' => (string) $voucher->code,
    'requirements' => ['otp'],
    'actions' => ['otp'],
    'meta' => [
        'provider' => 'paynamics',
        'authorization_type' => 'otp',
        'reference_id' => $referenceId,
        'otp_required' => true,
        'message' => 'Paynamics payout OTP is pending.',
    ],
    'messages' => [
        'Payout OTP approval required.',
    ],
]);
```

Reference ID should resolve to the Paynamics request id, typically:

```text
{voucher_code}-{account_number}
```

Example:

```text
TEST-KMHE-09171234567
```

Use payload account number if not available in the normalized disbursement:

```php
data_get($payload, 'bank_account.account_number')
    ?? data_get($payload, 'account_number')
    ?? data_get($payload, 'bank_account_no')
```

## Important: Clean Up Debug Logs Before Commit

The current diff includes debug logs in:

- `LifecycleApprovalOtpCompleter`
- `LifecycleClaimSubmitter`
- `UseDeferredPaynamicsOtpResolver`

Do not leave noisy logs in final code unless they are low-volume and intentionally useful.

Remove or downgrade these before commit:

```php
logger()->info('[LifecycleApprovalOtpCompleter] Invoked');
logger()->info('[UseDeferredPaynamicsOtpResolver] Activated');
logger()->info('[LifecycleClaimSubmitter] OTP approval mode', ...);
```

Keep only meaningful production logs if needed.

## Important: Do Not Break Existing Direct Mode

This command should remain direct/operator mode:

```bash
php artisan xchange:lifecycle:run basic_cash
```

Expected old UX should remain:

```text
[Paynamics OTP] OTP successfully sent...
Enter OTP:
```

Only this command should use the shared approval pipeline:

```bash
php artisan xchange:lifecycle:run basic_cash --approval-pipeline
```

## Tests To Add / Update

Add a unit test for `SubmitPayCodeClaim` that simulates:

- payload has:

```php
'approval' => [
    'pipeline' => true,
    'provider' => 'paynamics',
],
'bank_account' => [
    'account_number' => '09171234567',
],
```

- executor returns a `RedeemPayCodeResultData` that matches the swallowed deferred OTP path
- `SubmitPayCodeClaim` returns `ClaimApprovalInitiationResultData`
- `status` is `approval_required`
- `requirements` is `['otp']`
- `meta.provider` is `paynamics`
- `meta.reference_id` is `{voucher_code}-09171234567`
- `RecordVoucherClaim::handle()` is not called

Also keep these existing tests green:

```bash
./vendor/bin/pest tests/Unit/Actions/Claim/SubmitPayCodeClaimPendingPaynamicsOtpTest.php
./vendor/bin/pest tests/Unit/Lifecycle/Runners/Support/LifecycleClaimSubmitterTest.php
./vendor/bin/pest tests/Unit/Lifecycle/Runners/Support/LifecycleApprovalOtpCompleterTest.php
./vendor/bin/pest tests/Feature/Claim/PaynamicsApprovalOtpReplayAdapterTest.php
./vendor/bin/pest tests/Feature/Console/LifecycleScenarioEngineTest.php
./vendor/bin/pest tests/Feature/Console/LifecycleSequentialClaimsScenarioRunnerTest.php
```

## Final Live Validation

After tests pass:

```bash
php artisan optimize:clear
composer dump-autoload
php artisan xchange:lifecycle:run basic_cash --approval-pipeline --timeout=30 --poll=5
```

Success criteria:

```text
Approval required.
Provider: paynamics
Reference: TEST-XXXX-09171234567
Enter approval OTP:
```

Then enter the OTP received on the phone.

Expected continuation:

```text
Polling disbursement status...
...
Attempt [default]: SUCCEEDED as expected
```

## Architectural Reminder

The purpose of `--approval-pipeline` is not to improve operator UX. It is an architecture-verification mode proving that lifecycle runs can exercise the same approval flow used by the Claim UI:

```text
approval_required
→ SubmitClaimApprovalOtp
→ ClaimApprovalResumePayload
→ SubmitWebPayCodeClaim
→ Paynamics payout replay
```

Do not introduce a parallel OTP implementation.
Do not move this into Vue/Inertia/UI code.
This is backend orchestration only.
