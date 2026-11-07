<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LBHurtado\EmiCore\Data\PayoutAddressData;
use LBHurtado\EmiCore\Data\PayoutPartyData;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\EmiPaynamicsConstellation\Exceptions\PendingConstellationOtpException;
use LBHurtado\Voucher\Events\VoucherDisbursementFailed;
use LBHurtado\Voucher\Events\VoucherDisbursementSucceeded;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Jobs\Redemption\DispatchVoucherRedemptionFeedbackJob;
use LBHurtado\XChange\Listeners\RecordFailedVoucherDisbursement;
use LBHurtado\XChange\Listeners\RecordSuccessfulVoucherDisbursement;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Services\Execution\DurableTreasuryPayout;
use LBHurtado\XChange\Services\Execution\GuardedLegacyPayoutProvider;

it('protects an ordinary non-treasury claim with a durable submission and locality snapshot', function (): void {
    config()->set('x-change.payout_locality.enabled', true);
    config()->set('x-change.payout_locality.submission_guard_enabled', true);
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);
    $voucher = issueVoucher();
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(transactionId: 'SIMULATED-LEGACY', provider: 'netbank');

    $result = app(SubmitPayCodeClaim::class)->handle($voucher, [
        'mobile' => '09170000000',
        'recipient_country' => 'PH',
        'bank_account' => ['bank_code' => 'GXCHPHM2XXX', 'account_number' => '09170000000'],
        '_meta' => ['idempotency_key' => 'legacy-synthetic-claim'],
    ]);

    $this->artisan('x-change:claim:deliver-treasury-payouts', ['--limit' => 10])->assertSuccessful();
    $provider->assertDisburseCalledTimes(1);
    expect($result->claimed)->toBeTrue()
        ->and(DB::table('x_change_payout_submission_claims')->where('voucher_id', $voucher->getKey())->count())->toBe(1)
        ->and(DisbursementReconciliation::query()->where('voucher_id', $voucher->getKey())->first()?->payout_identity_snapshot)->toBeArray()
        ->and(data_get($voucher->fresh()->metadata, 'disbursement.cash_withdrawal_uuid'))->not->toBeEmpty()
        ->and(DB::table('x_change_treasury_payout_outbox')->where('voucher_id', $voucher->getKey())->value('status'))->toBe('completed');
    DB::table('x_change_treasury_payout_outbox')->where('voucher_id', $voucher->getKey())->update([
        'status' => 'processing', 'lease_token' => 'interrupted', 'lease_until' => now()->subMinute(),
    ]);
    $this->artisan('x-change:claim:deliver-treasury-payouts', ['--limit' => 10])->assertSuccessful();
    $provider->assertDisburseCalledTimes(1);
});

it('keeps a legacy provider approval challenge available to the same claim', function (): void {
    config()->set('x-change.payout_locality.enabled', true);
    config()->set('x-change.payout_locality.submission_guard_enabled', true);
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);
    $voucher = issueVoucher();
    $reference = $voucher->code.'-09170000000';
    $provider = fakePayoutProvider()->willThrow(PendingConstellationOtpException::fromPayload(['request_id' => $reference]));
    $claimKey = 'synthetic-legacy-approval';

    app(SubmitPayCodeClaim::class)->handle($voucher, [
        'mobile' => '09170000000', 'recipient_country' => 'PH',
        'bank_account' => ['bank_code' => 'GXCHPHM2XXX', 'account_number' => '09170000000'],
        '_meta' => ['idempotency_key' => $claimKey],
    ]);
    $this->artisan('x-change:claim:deliver-treasury-payouts', ['--limit' => 10])->assertSuccessful();

    expect(DB::table('x_change_treasury_payout_outbox')->where('voucher_id', $voucher->getKey())->value('status'))->toBe('approval_required')
        ->and(app(DurableTreasuryPayout::class)->approvalFor($voucher, $claimKey)['reference_id'])->toBe($reference)
        ->and(app(DurableTreasuryPayout::class)->approvalFor($voucher, 'another-claim'))->toBeNull();

    $provider->willReturnSuccessfulResult(transactionId: 'SIMULATED-APPROVED', provider: 'netbank');
    $result = app(SubmitPayCodeClaim::class)->handle($voucher->fresh(), [
        'mobile' => '09170000000', 'recipient_country' => 'PH',
        'bank_account' => ['bank_code' => 'GXCHPHM2XXX', 'account_number' => '09170000000'],
        '_meta' => ['idempotency_key' => $claimKey],
        'approval' => ['resume' => true, 'provider' => 'paynamics', 'reference_id' => $reference],
        'otp' => ['verified' => true],
    ]);
    expect($result->status)->toBe('succeeded')
        ->and(data_get($voucher->fresh()->metadata, 'disbursement.cash_withdrawal_uuid'))->not->toBeEmpty()
        ->and(DB::table('x_change_treasury_payout_outbox')->where('voucher_id', $voucher->getKey())->value('status'))->toBe('completed');
    $provider->assertDisburseCalledTimes(1);
});

it('guards and snapshots a legacy payout request before its provider call', function (): void {
    config()->set('x-change.payout_locality.enabled', true);
    config()->set('x-change.payout_locality.submission_guard_enabled', true);
    $voucher = issueVoucher();
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(transactionId: 'SIMULATED-GUARDED', provider: 'netbank');
    $gateway = app(GuardedLegacyPayoutProvider::class, ['voucher' => $voucher]);
    $request = new PayoutRequestData('synthetic-legacy-payout', 100, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY');

    $result = $gateway->disburse($request);

    expect($result->transaction_id)->toBe('SIMULATED-GUARDED')
        ->and(DB::table('x_change_payout_submission_claims')->where('voucher_id', $voucher->getKey())->count())->toBe(1)
        ->and(DisbursementReconciliation::query()->where('voucher_id', $voucher->getKey())->first()?->payout_identity_snapshot)->toBeArray();
    expect(fn () => $gateway->disburse($request))->toThrow(RuntimeException::class);
    $provider->assertDisburseCalledTimes(1);
});

it('does not submit or enqueue a legacy payout when redemption rolls back', function (): void {
    config()->set('x-change.payout_locality.enabled', true);
    config()->set('x-change.payout_locality.submission_guard_enabled', true);
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);
    $voucher = issueVoucher();
    $provider = fakePayoutProvider()->willReturnSuccessfulResult();

    DB::beginTransaction();
    try {
        app(SubmitPayCodeClaim::class)->handle($voucher, [
            'mobile' => '09170000000', 'recipient_country' => 'PH',
            'bank_account' => ['bank_code' => 'GXCHPHM2XXX', 'account_number' => '09170000000'],
            '_meta' => ['idempotency_key' => 'legacy-rollback'],
        ]);
        $provider->assertNoDisbursementAttempted();
    } finally {
        DB::rollBack();
    }

    expect(DB::table('x_change_treasury_payout_outbox')->where('voucher_id', $voucher->getKey())->count())->toBe(0);
    $provider->assertNoDisbursementAttempted();
});

it('preserves the existing legacy payout path when the guard is disabled', function (): void {
    config()->set('x-change.payout_locality.submission_guard_enabled', false);
    Bus::fake([DispatchVoucherRedemptionFeedbackJob::class]);
    $voucher = issueVoucher();
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(transactionId: 'SIMULATED-LEGACY-OFF');

    app(SubmitPayCodeClaim::class)->handle($voucher, [
        'mobile' => '09170000000', 'recipient_country' => 'PH',
        'bank_account' => ['bank_code' => 'GXCHPHM2XXX', 'account_number' => '09170000000'],
    ]);

    $provider->assertDisburseCalledTimes(1);
    expect(data_get($voucher->fresh()->metadata, 'disbursement.cash_withdrawal_uuid'))->not->toBeEmpty()
        ->and(DB::table('x_change_treasury_payout_outbox')->where('voucher_id', $voucher->getKey())->count())->toBe(0);
});

it('adds the approval column to an earlier outbox schema without affecting new installations', function (): void {
    Schema::table('x_change_treasury_payout_outbox', function (Blueprint $table): void {
        $table->dropColumn('approval_ciphertext');
    });
    $migration = require __DIR__.'/../../../database/migrations/2026_11_07_120000_add_approval_ciphertext_to_treasury_payout_outbox.php';

    $migration->up();
    expect(Schema::hasColumn('x_change_treasury_payout_outbox', 'approval_ciphertext'))->toBeTrue();
    $migration->up();
    expect(Schema::hasColumn('x_change_treasury_payout_outbox', 'approval_ciphertext'))->toBeTrue();
});

it('excludes locality and provider details from failed legacy payout audits', function (): void {
    $voucher = issueVoucher();
    $request = new PayoutRequestData('legacy-failed-private', 100, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY');
    $request->recipient = new PayoutPartyData('recipient', 'Private Person', new PayoutAddressData('Private Barangay', 'Manila', 'PH', '1000', 'supplied'));
    $exception = new RuntimeException('Provider included Private Barangay and 654321');

    app(RecordFailedVoucherDisbursement::class)->handle(new VoucherDisbursementFailed($voucher, $request, $exception));

    $row = DisbursementReconciliation::query()->where('voucher_id', $voucher->getKey())->sole();
    expect(json_encode($row->raw_request))->not->toContain('Private Barangay')->not->toContain('Private Person')
        ->and(json_encode($row->raw_response))->not->toContain('Private Barangay')->not->toContain('654321')
        ->and($row->error_message)->not->toContain('Private Barangay')->not->toContain('654321');
});

it('excludes locality and provider credentials from legacy payout audit results', function (): void {
    $voucher = issueVoucher();
    $request = new PayoutRequestData('legacy-private', 100, '09170000000', 'GXCHPHM2XXX', 'INSTAPAY');
    $request->recipient = new PayoutPartyData('recipient', 'Private Person', new PayoutAddressData('Private Barangay', 'Manila', 'PH', '1000', 'supplied'));
    $result = new PayoutResultData('TEST-PAYOUT', 'TEST-UUID', PayoutStatus::COMPLETED, 'paynamics', ['otp' => '654321', 'account' => '09170000000']);

    app(RecordSuccessfulVoucherDisbursement::class)->handle(new VoucherDisbursementSucceeded($voucher, $request, $result));

    $row = DisbursementReconciliation::query()->where('voucher_id', $voucher->getKey())->sole();
    expect(json_encode($row->raw_request))->not->toContain('Private Barangay')->not->toContain('Private Person')
        ->and(json_encode($row->raw_response))->not->toContain('654321')->not->toContain('09170000000');
});
