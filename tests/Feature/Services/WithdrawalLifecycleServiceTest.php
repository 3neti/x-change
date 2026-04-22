<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Contracts\WithdrawalExecutionContract;
use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use LBHurtado\XChange\Services\WithdrawalLifecycleService;

it('creates a withdrawal through the execution service', function () {
    $voucher = issueVoucher();

    $payload = [
        'voucher_code' => $voucher->code,
        'amount' => 100.00,
        'currency' => 'PHP',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ];

    $executionResult = new WithdrawPayCodeResultData(
        voucher_code: $voucher->code,
        withdrawn: true,
        status: 'withdrawn',
        requested_amount: 100.00,
        disbursed_amount: 100.00,
        currency: 'PHP',
        remaining_balance: 400.00,
        slice_number: 1,
        remaining_slices: 4,
        slice_mode: 'open',
        redeemer: [
            'mobile' => '09171234567',
            'country' => 'PH',
            'contact_id' => 1,
        ],
        bank_account: [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
        disbursement: [
            'status' => 'succeeded',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
            'transaction_id' => 'tx-001',
            'gateway' => 'test-gateway',
            'settlement_rail' => 'INSTAPAY',
        ],
        messages: ['Voucher withdrawal successful.'],
    );

    $access = Mockery::mock(VoucherAccessContract::class);
    $access->shouldReceive('findByCodeOrFail')
        ->once()
        ->with($voucher->code)
        ->andReturn($voucher);

    $execution = Mockery::mock(WithdrawalExecutionContract::class);
    $execution->shouldReceive('withdraw')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($executionResult);

    $reconciliations = Mockery::mock(DisbursementReconciliationStoreContract::class);

    $service = new WithdrawalLifecycleService($access, $execution, $reconciliations);

    $result = $service->create($payload);

    expect($result)->toBeArray()
        ->and($result['id'])->toBe('tx-001')
        ->and($result['voucher_code'])->toBe($voucher->code)
        ->and($result['status'])->toBe('withdrawn')
        ->and($result['amount'])->toBe(100.0)
        ->and($result['currency'])->toBe('PHP')
        ->and($result['bank_code'])->toBe('GXCHPHM2XXX')
        ->and($result['account_number'])->toBe('09171234567')
        ->and($result['messages'])->toBe(['Voucher withdrawal successful.']);
});

it('lists only withdrawal reconciliation records', function () {
    $withdraw = DisbursementReconciliationData::from([
        'id' => 1,
        'voucher_code' => 'TEST-WD-001',
        'claim_type' => 'withdraw',
        'status' => 'pending',
        'amount' => 100.00,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******4567',
    ]);

    $redeem = DisbursementReconciliationData::from([
        'id' => 2,
        'voucher_code' => 'TEST-RD-001',
        'claim_type' => 'redeem',
        'status' => 'succeeded',
        'amount' => 200.00,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******9999',
    ]);

    $access = Mockery::mock(VoucherAccessContract::class);
    $execution = Mockery::mock(WithdrawalExecutionContract::class);

    $reconciliations = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $reconciliations->shouldReceive('getPending')
        ->once()
        ->with(50)
        ->andReturn([$withdraw, $redeem]);

    $service = new WithdrawalLifecycleService($access, $execution, $reconciliations);

    $result = $service->list([]);

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0]['id'])->toBe('1')
        ->and($result[0]['voucher_code'])->toBe('TEST-WD-001')
        ->and($result[0]['status'])->toBe('pending')
        ->and($result[0]['amount'])->toBe(100.0)
        ->and($result[0]['currency'])->toBe('PHP');
});

it('filters listed withdrawals by voucher code and status', function () {
    $withdraw1 = DisbursementReconciliationData::from([
        'id' => 10,
        'voucher_code' => 'TEST-WD-001',
        'claim_type' => 'withdraw',
        'status' => 'pending',
        'amount' => 100.00,
        'currency' => 'PHP',
    ]);

    $withdraw2 = DisbursementReconciliationData::from([
        'id' => 11,
        'voucher_code' => 'TEST-WD-002',
        'claim_type' => 'withdraw',
        'status' => 'succeeded',
        'amount' => 200.00,
        'currency' => 'PHP',
    ]);

    $access = Mockery::mock(VoucherAccessContract::class);
    $execution = Mockery::mock(WithdrawalExecutionContract::class);

    $reconciliations = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $reconciliations->shouldReceive('getPending')
        ->once()
        ->with(50)
        ->andReturn([$withdraw1, $withdraw2]);

    $service = new WithdrawalLifecycleService($access, $execution, $reconciliations);

    $result = $service->list([
        'voucher_code' => 'TEST-WD-002',
        'status' => 'succeeded',
    ]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['id'])->toBe('11')
        ->and($result[0]['voucher_code'])->toBe('TEST-WD-002')
        ->and($result[0]['status'])->toBe('succeeded');
});

it('shows a withdrawal from a reconciliation record', function () {
    $record = DisbursementReconciliationData::from([
        'id' => 3,
        'voucher_code' => 'TEST-WD-003',
        'claim_type' => 'withdraw',
        'status' => 'pending_review',
        'amount' => 150.00,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******4567',
        'review_reason' => 'Gateway outcome uncertain',
        'error_message' => 'Timeout from provider',
    ]);

    $access = Mockery::mock(VoucherAccessContract::class);
    $execution = Mockery::mock(WithdrawalExecutionContract::class);

    $reconciliations = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $reconciliations->shouldReceive('findById')
        ->once()
        ->with(3)
        ->andReturn($record);

    $service = new WithdrawalLifecycleService($access, $execution, $reconciliations);

    $result = $service->show('3');

    expect($result)->toBeArray()
        ->and($result['id'])->toBe('3')
        ->and($result['voucher_code'])->toBe('TEST-WD-003')
        ->and($result['status'])->toBe('pending_review')
        ->and($result['amount'])->toBe(150.0)
        ->and($result['currency'])->toBe('PHP')
        ->and($result['bank_code'])->toBe('GXCHPHM2XXX')
        ->and($result['account_number'])->toBe('*******4567')
        ->and($result['messages'])->toBe([
            'Gateway outcome uncertain',
            'Timeout from provider',
        ]);
});

it('returns a placeholder when a withdrawal record cannot be found', function () {
    $access = Mockery::mock(VoucherAccessContract::class);
    $execution = Mockery::mock(WithdrawalExecutionContract::class);

    $reconciliations = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $reconciliations->shouldReceive('findById')
        ->once()
        ->with(999)
        ->andReturn(null);

    $service = new WithdrawalLifecycleService($access, $execution, $reconciliations);

    $result = $service->show('999');

    expect($result)->toBeArray()
        ->and($result['id'])->toBe('999')
        ->and($result['voucher_code'])->toBeNull()
        ->and($result['status'])->toBe('unknown')
        ->and($result['amount'])->toBeNull()
        ->and($result['currency'])->toBeNull()
        ->and($result['bank_code'])->toBeNull()
        ->and($result['account_number'])->toBeNull()
        ->and($result['messages'])->toBe([]);
});

it('uses the configured limit when listing withdrawals', function () {
    $access = Mockery::mock(VoucherAccessContract::class);
    $execution = Mockery::mock(WithdrawalExecutionContract::class);

    $reconciliations = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $reconciliations->shouldReceive('getPending')
        ->once()
        ->with(10)
        ->andReturn([]);

    $service = new WithdrawalLifecycleService($access, $execution, $reconciliations);

    $result = $service->list(['limit' => 10]);

    expect($result)->toBe([]);
});
