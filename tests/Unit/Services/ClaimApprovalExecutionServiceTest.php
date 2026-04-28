<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\ClaimApprovalWorkflowStoreContract;
use LBHurtado\XChange\Contracts\ClaimOtpVerificationContract;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Services\DefaultClaimApprovalExecutionService;

it('resumes claim submission after manual approval', function () {
    $voucher = new Voucher;
    $voucher->code = 'APPROVE-1234';

    $store = Mockery::mock(ClaimApprovalWorkflowStoreContract::class);
    $store->shouldReceive('get')->once()->with($voucher)->andReturn([
        'status' => 'pending',
        'requirements' => ['manual_approval'],
        'payload' => [
            'mobile' => '639171234567',
        ],
    ]);
    $store->shouldReceive('forget')->once()->with($voucher);

    $submit = Mockery::mock(SubmitPayCodeClaim::class);
    $submit->shouldReceive('handle')
        ->once()
        ->withArgs(function (Voucher $givenVoucher, array $payload): bool {
            return $givenVoucher->code === 'APPROVE-1234'
                && data_get($payload, 'approval.approved') === true
                && $payload['mobile'] === '639171234567';
        })
        ->andReturn(new SubmitPayCodeClaimResultData(
            voucher_code: 'APPROVE-1234',
            claim_type: 'withdraw',
            claimed: true,
            status: 'succeeded',
        ));

    $otpVerification = Mockery::mock(\LBHurtado\XChange\Contracts\ClaimOtpVerificationContract::class);
    $otpVerification->shouldReceive('verify')->andReturnTrue();

    $service = new DefaultClaimApprovalExecutionService(
        $store,
        $submit,
        $otpVerification,
    );

    $result = $service->approve($voucher, [
        'approved_by' => 'tester',
    ]);

    expect($result->status)->toBe('succeeded');
});

it('resumes claim submission after otp verification', function () {
    $voucher = new Voucher;
    $voucher->code = 'OTP-1234';

    $store = Mockery::mock(ClaimApprovalWorkflowStoreContract::class);
    $store->shouldReceive('get')->once()->with($voucher)->andReturn([
        'status' => 'pending',
        'requirements' => ['otp'],
        'payload' => [
            'mobile' => '639171234567',
        ],
    ]);
    $store->shouldReceive('forget')->once()->with($voucher);

    $submit = Mockery::mock(SubmitPayCodeClaim::class);
    $submit->shouldReceive('handle')
        ->once()
        ->withArgs(function (Voucher $givenVoucher, array $payload): bool {
            return $givenVoucher->code === 'OTP-1234'
                && data_get($payload, 'otp.otp_code') === '123456'
                && data_get($payload, 'otp.verified') === true;
        })
        ->andReturn(new SubmitPayCodeClaimResultData(
            voucher_code: 'OTP-1234',
            claim_type: 'withdraw',
            claimed: true,
            status: 'succeeded',
        ));

    $otpVerification = Mockery::mock(\LBHurtado\XChange\Contracts\ClaimOtpVerificationContract::class);
    $otpVerification->shouldReceive('verify')->andReturnTrue();

    $service = new DefaultClaimApprovalExecutionService(
        $store,
        $submit,
        $otpVerification,
    );

    $result = $service->verifyOtp($voucher, [
        'otp' => '123456',
    ]);

    expect($result->status)->toBe('succeeded');
});

it('fails when no pending approval workflow exists', function () {
    $voucher = new Voucher;
    $voucher->code = 'MISSING-WORKFLOW';

    $store = Mockery::mock(ClaimApprovalWorkflowStoreContract::class);
    $store->shouldReceive('get')->once()->with($voucher)->andReturn(null);

    $submit = Mockery::mock(SubmitPayCodeClaim::class);

    $otpVerification = Mockery::mock(\LBHurtado\XChange\Contracts\ClaimOtpVerificationContract::class);
    $otpVerification->shouldReceive('verify')->andReturnTrue();

    $service = new DefaultClaimApprovalExecutionService(
        $store,
        $submit,
        $otpVerification,
    );

    expect(fn () => $service->approve($voucher, []))
        ->toThrow(RuntimeException::class, 'No pending claim approval workflow found.');
});

it('replays claim after manual approval and clears workflow after success', function () {
    $voucher = new Voucher;
    $voucher->code = 'APPROVE-REPLAY';

    $workflowPayload = [
        'mobile' => '639171234567',
        'amount' => 1000,
    ];

    $store = Mockery::mock(ClaimApprovalWorkflowStoreContract::class);
    $store->shouldReceive('get')->once()->with($voucher)->andReturn([
        'status' => 'pending',
        'requirements' => ['manual_approval'],
        'payload' => $workflowPayload,
    ]);
    $store->shouldReceive('forget')->once()->with($voucher);

    $submit = Mockery::mock(SubmitPayCodeClaim::class);
    $submit->shouldReceive('handle')
        ->once()
        ->withArgs(function (Voucher $givenVoucher, array $payload): bool {
            return $givenVoucher->code === 'APPROVE-REPLAY'
                && $payload['mobile'] === '639171234567'
                && data_get($payload, 'approval.resume') === true
                && data_get($payload, 'approval.approved') === true;
        })
        ->andReturn(new SubmitPayCodeClaimResultData(
            voucher_code: 'APPROVE-REPLAY',
            claim_type: 'withdraw',
            claimed: true,
            status: 'succeeded',
        ));

    $otpVerification = Mockery::mock(ClaimOtpVerificationContract::class);
    $otpVerification->shouldReceive('verify')->never();

    $service = new DefaultClaimApprovalExecutionService(
        $store,
        $submit,
        $otpVerification,
    );

    $result = $service->approve($voucher, [
        'approved_by' => 'tester',
    ]);

    expect($result->status)->toBe('succeeded');
});

it('replays claim after otp verification and clears workflow after success', function () {
    $voucher = new Voucher;
    $voucher->code = 'OTP-REPLAY';

    $workflowPayload = [
        'mobile' => '639171234567',
        'amount' => 1000,
    ];

    $store = Mockery::mock(ClaimApprovalWorkflowStoreContract::class);
    $store->shouldReceive('get')->once()->with($voucher)->andReturn([
        'status' => 'pending',
        'requirements' => ['otp'],
        'payload' => $workflowPayload,
    ]);
    $store->shouldReceive('forget')->once()->with($voucher);

    $submit = Mockery::mock(SubmitPayCodeClaim::class);
    $submit->shouldReceive('handle')
        ->once()
        ->withArgs(function (Voucher $givenVoucher, array $payload): bool {
            return $givenVoucher->code === 'OTP-REPLAY'
                && data_get($payload, 'approval.resume') === true
                && data_get($payload, 'otp.verified') === true
                && data_get($payload, 'otp.otp_code') === '123456';
        })
        ->andReturn(new SubmitPayCodeClaimResultData(
            voucher_code: 'OTP-REPLAY',
            claim_type: 'withdraw',
            claimed: true,
            status: 'succeeded',
        ));

    $otpVerification = Mockery::mock(ClaimOtpVerificationContract::class);
    $otpVerification->shouldReceive('verify')
        ->once()
        ->with($voucher, '123456', Mockery::type('array'))
        ->andReturnTrue();

    $service = new DefaultClaimApprovalExecutionService(
        $store,
        $submit,
        $otpVerification,
    );

    $result = $service->verifyOtp($voucher, [
        'otp' => '123456',
    ]);

    expect($result->status)->toBe('succeeded');
});

it('does not clear workflow when replay fails', function () {
    $voucher = new Voucher;
    $voucher->code = 'REPLAY-FAIL';

    $store = Mockery::mock(ClaimApprovalWorkflowStoreContract::class);
    $store->shouldReceive('get')->once()->with($voucher)->andReturn([
        'status' => 'pending',
        'requirements' => ['manual_approval'],
        'payload' => [
            'mobile' => '639171234567',
        ],
    ]);
    $store->shouldReceive('forget')->never();

    $submit = Mockery::mock(SubmitPayCodeClaim::class);
    $submit->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Replay failed.'));

    $otpVerification = Mockery::mock(ClaimOtpVerificationContract::class);
    $otpVerification->shouldReceive('verify')->never();

    $service = new DefaultClaimApprovalExecutionService(
        $store,
        $submit,
        $otpVerification,
    );

    expect(fn () => $service->approve($voucher, []))
        ->toThrow(RuntimeException::class, 'Replay failed.');
});
