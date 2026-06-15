# Paynamics Deferred OTP Approval Pipeline

## Purpose

This note documents the Paynamics OTP patches introduced while validating lifecycle scenario runs against the Constellation payout provider.

The key outcome is that Paynamics payout OTP is now modeled as a claim approval state instead of an inline provider prompt. This keeps claim UI/UX extraction and lifecycle automation aligned:

```text
Paynamics asks for payout OTP
    -> claim returns approval_required
    -> approval OTP is submitted
    -> claim replay resumes only the payout leg
    -> reconciliation follows provider status
```

## Required Configuration

For Paynamics lifecycle approval testing, the host app needs:

```env
XCHANGE_PAYOUT_PROVIDER='LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationPayoutProvider'
XCHANGE_WITHDRAWAL_OTP_DRIVER=paynamics
X_CHANGE_CLAIM_APPROVAL_OTP_DRIVER=withdrawal_otp
```

`XCHANGE_PAYOUT_PROVIDER` chooses the default payout provider used by x-change. The two OTP vars choose the provider-backed claim approval verifier.

The lifecycle `--provider=` option is stronger than the default payout provider for that run. For example:

```bash
php artisan xchange:lifecycle:run basic_cash --provider=netbank
```

binds Netbank for the scenario even if `XCHANGE_PAYOUT_PROVIDER` is Paynamics. Paynamics deferred OTP behavior must not activate for non-Paynamics providers.

## Provider Selection Rule

`--approval-pipeline` is not the same thing as "Paynamics mode".

The lifecycle submitter now activates deferred Paynamics OTP only when both are true:

```text
1. the run wants JSON or approval-pipeline handling
2. the active PayoutProvider is ConstellationPayoutProvider
```

This matters because Netbank can be selected with:

```bash
php artisan xchange:lifecycle:run basic_cash --approval-pipeline --provider=netbank
```

That run should not inject:

```php
[
    'approval' => [
        'pipeline' => true,
        'provider' => 'paynamics',
    ],
]
```

Paynamics approval metadata is provider-specific. It should only exist when the active provider is Paynamics/Constellation.

## Internal Flow

### 1. Claim Submission

Lifecycle claims route through:

```text
LifecycleClaimSubmitter
    -> SubmitPayCodeClaim
```

When the active payout provider is Paynamics and the run is JSON or `--approval-pipeline`, the submitter wraps the claim in:

```text
UseDeferredPaynamicsOtpResolver
```

That wrapper temporarily swaps the Paynamics `ConstellationOtpResolver` to `DeferredOtpResolver`.

### 2. Deferred OTP Capture

`ConstellationPayoutProvider` requests OTP before cash-out. With `DeferredOtpResolver`, the OTP request is recorded in:

```text
ClaimApprovalPendingOtpStore
```

The provider then raises or records an OTP-pending state instead of prompting interactively.

`SubmitPayCodeClaim` detects Paynamics OTP pending state from three sources:

```text
1. PendingConstellationOtpException
2. ClaimApprovalPendingOtpStore by reference
3. voucher metadata disbursement status/error
```

This is needed because the lower voucher pipeline can swallow the provider OTP exception, mark the voucher redeemed, and store the pending disbursement only in voucher metadata.

The claim response becomes:

```text
status: approval_required
requirements: [otp]
meta.provider: paynamics
meta.reference_id: {voucher}-{account}
```

### 3. OTP Submission

Lifecycle interactive completion uses:

```text
LifecycleApprovalOtpCompleter
    -> SubmitClaimApprovalOtp
    -> configured claim approval OTP verifier
```

The completer passes the original claim payload into OTP verification. This is important because `WithdrawalOtpApprovalBackedClaimOtpVerificationService` needs the claimant mobile and the Paynamics reference id.

The verifier now resolves:

```text
mobile:
    workflow.mobile
    workflow.payload.mobile
    workflow.payload.redeemer.mobile
    workflow.payload.owner_mobile

reference:
    workflow.reference_id
    workflow.approval.reference_id
    workflow.voucher_code
```

With the `withdrawal_otp` claim approval driver, the OTP is submitted into the Paynamics pending OTP store and the approval result can complete.

### 4. Replay After OTP

After OTP verification, the lifecycle runner builds an approval resume payload:

```php
[
    'approval' => [
        'resume' => true,
        'provider' => 'paynamics',
        'reference_id' => $referenceId,
        'authorization_type' => 'otp',
    ],
    'otp' => [
        'verified' => true,
        'code' => $otp,
    ],
]
```

The replay enters `SubmitPayCodeClaim` again.

The first redemption attempt already marked the voucher redeemed, so a naive replay can fail with:

```text
Voucher has already been redeemed.
```

or the broader wrapped message:

```text
Failed to redeem voucher.
```

For verified Paynamics approval replays, `SubmitPayCodeClaim` now detects this case and resumes only the payout leg through:

```text
WithdrawalDisbursementExecutor
```

The replay guard is intentionally narrow:

```text
approval.resume=true
approval.provider=paynamics
otp.verified=true
reference matches voucher disbursement metadata when both are available
voucher metadata still indicates OTP pending
```

This avoids treating unrelated redemption failures as payout replay candidates.

## Reconciliation Expectations

A successful OTP submission does not mean the beneficiary has already received money.

For Paynamics, accepted OTP commonly returns a cash-out response like:

```text
response_code: GR162
response_message: Cash Out Pending
```

The x-change result may therefore be:

```text
claim status: redeemed
disbursement status: pending
```

Provider polling may then see one of several states:

```text
Cash Out Pending
PRE-DEBIT / WITHHELD wallet transaction
Cash Out Failed
settled/succeeded
```

During live testing, Paynamics accepted corrected OTPs and created cash-out records, but the status endpoint sometimes reported `Cash Out Failed` while the wallet transaction endpoint showed `PRE-DEBIT` / `WITHHELD`.

That is a provider settlement/reconciliation concern, not an OTP prompt concern.

## Useful Commands

Paynamics approval-pipeline run:

```bash
php artisan xchange:lifecycle:run basic_cash --approval-pipeline --timeout=30 --poll=5
```

Explicit Paynamics provider:

```bash
php artisan xchange:lifecycle:run basic_cash --approval-pipeline --provider=paynamics --timeout=30 --poll=5
```

Netbank run, ignoring Paynamics OTP approval behavior:

```bash
php artisan xchange:lifecycle:run basic_cash --provider=netbank
```

Check a voucher reconciliation:

```bash
php artisan xchange:disbursement:check TEST-CODE --sync --json
```

Check Paynamics cash-out status by request id:

```bash
php artisan constellation:cash-out-status TEST-CODE-09171234567
```

Check Paynamics wallet balance:

```bash
php artisan constellation:wallet-balance CNSTWLLT9GSPQ1
```

## Patched Components

`SubmitPayCodeClaim`

Handles Paynamics OTP pending states, converts them to approval-required results, and replays only the payout leg after approval OTP verification when the voucher was already redeemed.

`LifecycleClaimSubmitter`

Wraps lifecycle claims in `UseDeferredPaynamicsOtpResolver` only when the active payout provider is `ConstellationPayoutProvider`.

`UseDeferredPaynamicsOtpResolver`

Temporarily swaps `ConstellationOtpResolver` to `DeferredOtpResolver` and refreshes the Paynamics payout provider instance so it uses the deferred resolver.

`LifecycleApprovalOtpCompleter`

Passes the base claim payload into approval OTP submission so mobile/account context is available during verification.

`WithdrawalOtpApprovalBackedClaimOtpVerificationService`

Reads top-level mobile/reference fields as well as nested payload fields, allowing lifecycle approval submission to resolve the correct Paynamics OTP reference.

`config/x-change.php`

Adds `claim_approval.otp` configuration with `null` and `withdrawal_otp` drivers.

## Regression Tests

The relevant test slice is:

```bash
./vendor/bin/pest \
  tests/Unit/Actions/Claim/SubmitPayCodeClaimPendingPaynamicsOtpTest.php \
  tests/Unit/Lifecycle/Runners/Support/LifecycleClaimSubmitterTest.php \
  tests/Unit/Lifecycle/Runners/Support/LifecycleApprovalOtpCompleterTest.php \
  tests/Feature/Claim/PaynamicsApprovalOtpReplayAdapterTest.php \
  tests/Feature/Console/LifecycleScenarioEngineTest.php \
  tests/Feature/Console/LifecycleSequentialClaimsScenarioRunnerTest.php \
  tests/Unit/Services/WithdrawalPipelineSteps/AuthorizeWithdrawalOtpStepTest.php \
  tests/Unit/Actions/Claim/SubmitClaimApprovalOtpConfiguredDriverTest.php \
  --compact
```

Expected result after these patches:

```text
19 passed
```

Also run Pint after PHP changes:

```bash
vendor/bin/pint --dirty --format agent
```

## Debugging Notes

If a lifecycle run prompts for OTP and then returns:

```text
actual=pending_approval
```

check:

```bash
php artisan config:show x-change.claim_approval.otp.driver
php artisan config:show x-change.withdrawal.otp.driver
```

Both must be configured for Paynamics approval testing:

```text
x-change.claim_approval.otp.driver = withdrawal_otp
x-change.withdrawal.otp.driver = paynamics
```

If the OTP reaches Paynamics but cash-out status remains unresolved, inspect both:

```text
withdraw/get_by_request_id
elastic_trx/get_by_request_id
```

The cash-out status endpoint and wallet transaction endpoint can disagree during pending/withheld states.

If `--provider=netbank` is used, Paynamics OTP approval should not run. Netbank timeouts or `invalid transaction id` status errors should be debugged as Netbank provider/reconciliation issues, not Paynamics approval issues.
