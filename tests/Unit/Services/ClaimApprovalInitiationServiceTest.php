<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimApprovalNotificationContract;
use LBHurtado\XChange\Contracts\ClaimApprovalWorkflowStoreContract;
use LBHurtado\XChange\Contracts\ClaimOtpChallengeContract;
use LBHurtado\XChange\Services\DefaultClaimApprovalInitiationService;

it('stores a pending approval workflow', function () {
    $voucher = new Voucher;
    $voucher->code = 'APPROVAL-1234';

    $payload = [
        'mobile' => '639171234567',
        'amount' => 1000,
    ];

    $approval = [
        'status' => 'pending',
        'requirements' => ['manual_approval'],
        'actions' => ['manual_approval'],
        'meta' => [
            'reason' => 'approval_required',
        ],
    ];

    $store = Mockery::mock(ClaimApprovalWorkflowStoreContract::class);
    $store->shouldReceive('put')
        ->once()
        ->withArgs(function (Voucher $givenVoucher, array $workflow) use ($voucher, $payload): bool {
            return $givenVoucher === $voucher
                && $workflow['status'] === 'pending'
                && $workflow['voucher_code'] === 'APPROVAL-1234'
                && $workflow['requirements'] === ['manual_approval']
                && $workflow['actions'] === ['manual_approval']
                && $workflow['payload'] === $payload
                && isset($workflow['created_at']);
        });

    $notifications = Mockery::mock(ClaimApprovalNotificationContract::class);
    $notifications->shouldReceive('notify')->once();

    $otp = Mockery::mock(ClaimOtpChallengeContract::class);
    $otp->shouldReceive('request')->never();

    $service = new DefaultClaimApprovalInitiationService(
        $store,
        $notifications,
        $otp,
    );

    $result = $service->initiate($voucher, $payload, $approval);

    expect($result->voucher_code)->toBe('APPROVAL-1234');
    expect($result->status)->toBe('pending_approval');
    expect($result->requirements)->toBe(['manual_approval']);
    expect($result->actions)->toBe(['manual_approval']);
    expect($result->messages)->toContain('Claim approval workflow initiated.');
});

it('requests otp challenge when otp is required', function () {
    $voucher = new Voucher;
    $voucher->code = 'OTP-1234';

    $payload = [
        'mobile' => '639171234567',
        'amount' => 1000,
    ];

    $approval = [
        'status' => 'pending',
        'requirements' => ['otp'],
        'actions' => ['otp'],
    ];

    $store = Mockery::mock(ClaimApprovalWorkflowStoreContract::class);
    $store->shouldReceive('put')
        ->once()
        ->withArgs(function (Voucher $givenVoucher, array $workflow): bool {
            return $givenVoucher->code === 'OTP-1234'
                && data_get($workflow, 'otp.driver') === 'fake'
                && data_get($workflow, 'otp.requested') === true
                && data_get($workflow, 'otp.reference') === 'OTP-REF-123';
        });

    $notifications = Mockery::mock(ClaimApprovalNotificationContract::class);
    $notifications->shouldReceive('notify')->once();

    $otp = Mockery::mock(ClaimOtpChallengeContract::class);
    $otp->shouldReceive('request')
        ->once()
        ->withArgs(fn (Voucher $givenVoucher, array $workflow): bool => $givenVoucher === $voucher
            && $workflow['requirements'] === ['otp'])
        ->andReturn([
            'driver' => 'fake',
            'requested' => true,
            'reference' => 'OTP-REF-123',
        ]);

    $service = new DefaultClaimApprovalInitiationService(
        $store,
        $notifications,
        $otp,
    );

    $result = $service->initiate($voucher, $payload, $approval);

    expect($result->status)->toBe('pending_approval');
    expect($result->meta)->toMatchArray([
        'otp' => [
            'driver' => 'fake',
            'requested' => true,
            'reference' => 'OTP-REF-123',
        ],
    ]);
});

it('notifies approval channel after storing workflow', function () {
    $voucher = new Voucher;
    $voucher->code = 'NOTIFY-1234';

    $payload = [
        'mobile' => '639171234567',
    ];

    $approval = [
        'status' => 'pending',
        'requirements' => ['manual_approval'],
        'actions' => ['manual_approval'],
    ];

    $store = Mockery::mock(ClaimApprovalWorkflowStoreContract::class);
    $store->shouldReceive('put')->once()->ordered();

    $notifications = Mockery::mock(ClaimApprovalNotificationContract::class);
    $notifications->shouldReceive('notify')
        ->once()
        ->ordered()
        ->withArgs(function (Voucher $givenVoucher, array $workflow): bool {
            return $givenVoucher->code === 'NOTIFY-1234'
                && $workflow['status'] === 'pending';
        });

    $otp = Mockery::mock(ClaimOtpChallengeContract::class);
    $otp->shouldReceive('request')->never();

    $service = new DefaultClaimApprovalInitiationService(
        $store,
        $notifications,
        $otp,
    );

    $result = $service->initiate($voucher, $payload, $approval);

    expect($result->status)->toBe('pending_approval');
});

it('returns pending approval initiation result', function () {
    $voucher = new Voucher;
    $voucher->code = 'RESULT-1234';

    $store = Mockery::mock(ClaimApprovalWorkflowStoreContract::class);
    $store->shouldReceive('put')->once();

    $notifications = Mockery::mock(ClaimApprovalNotificationContract::class);
    $notifications->shouldReceive('notify')->once();

    $otp = Mockery::mock(ClaimOtpChallengeContract::class);
    $otp->shouldReceive('request')->never();

    $service = new DefaultClaimApprovalInitiationService(
        $store,
        $notifications,
        $otp,
    );

    $result = $service->initiate($voucher, [], [
        'status' => 'pending',
        'requirements' => ['manual_approval'],
        'actions' => ['manual_approval'],
    ]);

    expect($result->voucher_code)->toBe('RESULT-1234');
    expect($result->status)->toBe('pending_approval');
    expect($result->requirements)->toBe(['manual_approval']);
    expect($result->actions)->toBe(['manual_approval']);
    expect($result->meta)->toHaveKey('workflow');
});
