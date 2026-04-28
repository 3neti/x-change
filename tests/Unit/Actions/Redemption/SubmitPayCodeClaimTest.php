<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RecordVoucherClaim;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\ApprovalWorkflowContract;
use LBHurtado\XChange\Contracts\ClaimApprovalInitiationContract;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\ClaimExecutorContract;
use LBHurtado\XChange\Contracts\SettlementExecutionContract;
use LBHurtado\XChange\Data\ApprovalWorkflowResultData;
use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use LBHurtado\XChange\Data\Settlement\SettlementExecutionResultData;
use LBHurtado\XChange\Models\VoucherClaim;

it('submits a claim through the selected executor and normalizes redeem result', function () {
    $voucher = Mockery::mock(Voucher::class);

    $payload = [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'secret' => '1234',
        'amount' => 100.00,
        'inputs' => [
            'name' => 'Juan Dela Cruz',
        ],
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ];

    $executorResult = new RedeemPayCodeResultData(
        voucher_code: 'TEST-1234',
        redeemed: true,
        status: 'redeemed',
        redeemer: [
            'mobile' => '09171234567',
            'country' => 'PH',
        ],
        bank_account: [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
        inputs: [
            'name' => 'Juan Dela Cruz',
        ],
        disbursement: [
            'status' => 'requested',
        ],
        messages: ['Voucher redeemed successfully.'],
    );

    $executor = Mockery::mock(ClaimExecutorContract::class);
    $executor->shouldReceive('handle')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($executorResult);

    $factory = Mockery::mock(ClaimExecutionFactoryContract::class);
    $factory->shouldReceive('make')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($executor);

    $recordVoucherClaim = Mockery::mock(RecordVoucherClaim::class);
    $recordVoucherClaim
        ->shouldReceive('handle')
        ->once()
        ->with($voucher, Mockery::type(SubmitPayCodeClaimResultData::class), $payload)
        ->andReturn(new VoucherClaim);

    $action = new SubmitPayCodeClaim($factory, $recordVoucherClaim);

    $result = $action->handle($voucher, $payload);

    expect($result)->toBeInstanceOf(SubmitPayCodeClaimResultData::class);
    expect($result->voucher_code)->toBe('TEST-1234');
    expect($result->claim_type)->toBe('redeem');
    expect($result->claimed)->toBeTrue();
    expect($result->status)->toBe('redeemed');
    expect($result->requested_amount)->toBeNull();
    expect($result->disbursed_amount)->toBeNull();
    expect($result->currency)->toBeNull();
    expect($result->remaining_balance)->toBeNull();
    expect($result->fully_claimed)->toBeTrue();
    expect($result->disbursement)->toBe([
        'status' => 'requested',
    ]);
    expect($result->messages)->toBe([
        'Voucher redeemed successfully.',
    ]);
});

it('submits a claim through the selected executor and normalizes withdraw result', function () {
    $voucher = Mockery::mock(Voucher::class);

    $payload = [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'amount' => 200.00,
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ];

    $executorResult = new WithdrawPayCodeResultData(
        voucher_code: 'TEST-9999',
        withdrawn: true,
        status: 'withdrawn',
        requested_amount: 200.00,
        disbursed_amount: 200.00,
        currency: 'PHP',
        remaining_balance: 300.00,
        slice_number: 2,
        remaining_slices: 3,
        slice_mode: 'open',
        redeemer: [
            'mobile' => '09171234567',
            'country' => 'PH',
        ],
        bank_account: [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
        disbursement: [
            'status' => 'requested',
        ],
        messages: ['Voucher withdrawal successful.'],
    );

    $executor = Mockery::mock(ClaimExecutorContract::class);
    $executor->shouldReceive('handle')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($executorResult);

    $factory = Mockery::mock(ClaimExecutionFactoryContract::class);
    $factory->shouldReceive('make')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($executor);

    $recordVoucherClaim = Mockery::mock(RecordVoucherClaim::class);
    $recordVoucherClaim
        ->shouldReceive('handle')
        ->once()
        ->with($voucher, Mockery::type(SubmitPayCodeClaimResultData::class), $payload)
        ->andReturn(new VoucherClaim);

    $action = new SubmitPayCodeClaim($factory, $recordVoucherClaim);

    $result = $action->handle($voucher, $payload);

    expect($result)->toBeInstanceOf(SubmitPayCodeClaimResultData::class);
    expect($result->voucher_code)->toBe('TEST-9999');
    expect($result->claim_type)->toBe('withdraw');
    expect($result->claimed)->toBeTrue();
    expect($result->status)->toBe('withdrawn');
    expect($result->requested_amount)->toBe(200.0);
    expect($result->disbursed_amount)->toBe(200.0);
    expect($result->currency)->toBe('PHP');
    expect($result->remaining_balance)->toBe(300.0);
    expect($result->fully_claimed)->toBeFalse();
    expect($result->disbursement)->toBe([
        'status' => 'requested',
    ]);
    expect($result->messages)->toBe([
        'Voucher withdrawal successful.',
    ]);
});

it('throws for unsupported executor result types', function () {
    $voucher = Mockery::mock(Voucher::class);

    $payload = [
        'mobile' => '09171234567',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
        'inputs' => [],
    ];

    $executor = Mockery::mock(ClaimExecutorContract::class);
    $executor->shouldReceive('handle')
        ->once()
        ->with($voucher, $payload)
        ->andReturn((object) ['unexpected' => true]);

    $factory = Mockery::mock(ClaimExecutionFactoryContract::class);
    $factory->shouldReceive('make')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($executor);

    $recordVoucherClaim = Mockery::mock(RecordVoucherClaim::class);
    $recordVoucherClaim->shouldNotReceive('handle');

    $action = new SubmitPayCodeClaim($factory, $recordVoucherClaim);

    expect(fn () => $action->handle($voucher, $payload))
        ->toThrow(RuntimeException::class, 'Unsupported claim execution result type');
});

it('submits settlement claims through the selected settlement executor and normalizes result', function () {
    $voucher = new Voucher;
    $voucher->code = 'SETTLE-1234';

    $executor = Mockery::mock(SettlementExecutionContract::class);

    $payload = [
        'mobile' => '639171234567',
        'amount' => 1000,
    ];

    $executor->shouldReceive('execute')
        ->once()
        ->with($voucher, $payload)
        ->andReturn(new SettlementExecutionResultData(
            voucher_code: 'SETTLE-1234',
            status: 'pending',
            message: 'Settlement execution is pending.',
            meta: [
                'settlement_mode' => 'stub',
            ],
        ));

    $factory = Mockery::mock(ClaimExecutionFactoryContract::class);
    $factory->shouldReceive('make')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($executor);

    $recorder = Mockery::mock(RecordVoucherClaim::class);
    $recorder->shouldReceive('handle')->never();

    $action = new SubmitPayCodeClaim(
        $factory,
        $recorder,
    );

    $result = $action->handle($voucher, $payload);

    expect($result->voucher_code)->toBe('SETTLE-1234');
    expect($result->claim_type)->toBe('settlement');
    expect($result->claimed)->toBeFalse();
    expect($result->status)->toBe('pending');
    expect($result->messages)->toContain('Settlement execution is pending.');
    expect($result->settlement)->toMatchArray([
        'settlement_mode' => 'stub',
    ]);
});

it('initiates approval workflow when withdrawal result requires approval', function () {
    $voucher = new Voucher;
    $voucher->code = 'APPROVAL-1234';

    $payload = [
        'mobile' => '639171234567',
        'amount' => 1000,
    ];

    $withdrawResult = new WithdrawPayCodeResultData(
        voucher_code: 'APPROVAL-1234',
        withdrawn: false,
        status: 'approval_required',
        requested_amount: 1000,
        disbursed_amount: 0,
        currency: 'PHP',
        remaining_balance: 1000,
        slice_number: null,
        remaining_slices: null,
        slice_mode: null,
        redeemer: [],
        bank_account: [],
        disbursement: [],
        messages: ['Approval required.'],
        approval_requirements: ['otp'],
        approval_meta: [
            'reason' => 'approval_required',
        ],
    );

    $executor = Mockery::mock(ClaimExecutorContract::class);
    $executor->shouldReceive('handle')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($withdrawResult);

    $factory = Mockery::mock(ClaimExecutionFactoryContract::class);
    $factory->shouldReceive('make')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($executor);

    $workflow = Mockery::mock(ApprovalWorkflowContract::class);
    $workflow->shouldReceive('resolve')
        ->once()
        ->withArgs(function ($result, array $context) use ($withdrawResult, $payload): bool {
            return $result === $withdrawResult
                && $context['voucher_code'] === 'APPROVAL-1234'
                && $context['payload'] === $payload;
        })
        ->andReturn(new ApprovalWorkflowResultData(
            status: 'pending',
            requirements: ['otp'],
            meta: [
                'reason' => 'approval_required',
            ],
        ));

    $initiation = Mockery::mock(ClaimApprovalInitiationContract::class);
    $initiation->shouldReceive('initiate')
        ->once()
        ->withArgs(function (Voucher $givenVoucher, array $givenPayload, array $approval): bool {
            return $givenVoucher->code === 'APPROVAL-1234'
                && $givenPayload['mobile'] === '639171234567'
                && $approval['status'] === 'pending'
                && $approval['requirements'] === ['otp'];
        })
        ->andReturn(new ClaimApprovalInitiationResultData(
            voucher_code: 'APPROVAL-1234',
            status: 'pending_approval',
            requirements: ['otp'],
            actions: ['otp'],
            meta: [],
            messages: ['Claim approval workflow initiated.'],
        ));

    $recorder = Mockery::mock(RecordVoucherClaim::class);
    $recorder->shouldReceive('handle')->never();

    $action = new SubmitPayCodeClaim(
        $factory,
        $recorder,
        $workflow,
        $initiation,
    );

    $result = $action->handle($voucher, $payload);

    expect($result)->toBeInstanceOf(ClaimApprovalInitiationResultData::class);
    expect($result->status)->toBe('pending_approval');
});
