<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\ClaimApprovalWorkflowStoreContract;
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

    $service = new DefaultClaimApprovalExecutionService($store, $submit);

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

    $service = new DefaultClaimApprovalExecutionService($store, $submit);

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

    $service = new DefaultClaimApprovalExecutionService($store, $submit);

    expect(fn () => $service->approve($voucher, []))
        ->toThrow(RuntimeException::class, 'No pending claim approval workflow found.');
});
