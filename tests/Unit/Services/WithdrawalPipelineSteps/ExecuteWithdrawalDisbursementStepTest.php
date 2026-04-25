<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\XChange\Data\WithdrawalDisbursementExecutionData;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Services\WithdrawalDisbursementExecutor;
use LBHurtado\XChange\Services\WithdrawalPendingDisbursementRecorder;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\ExecuteWithdrawalDisbursementStep;

it('executes disbursement and stores execution on context', function () {
    $voucher = issueVoucher();

    $input = PayoutRequestData::from([
        'reference' => $voucher->code.'-09173011987-S1',
        'amount' => 100.00,
        'account_number' => '09173011987',
        'bank_code' => 'GXCHPHM2XXX',
        'settlement_rail' => 'INSTAPAY',
    ]);

    $execution = new WithdrawalDisbursementExecutionData(
        input: $input,
        response: PayoutResultData::from([
            'uuid' => (string) Str::uuid(),
            'transaction_id' => 'TXN-123',
            'status' => PayoutStatus::PENDING,
            'provider' => 'netbank',
            'raw' => [],
        ]),
        status: 'pending',
        message: null,
    );

    $executor = Mockery::mock(WithdrawalDisbursementExecutor::class);
    $executor->shouldReceive('execute')
        ->once()
        ->withArgs(fn ($v, $i, $s) => $v->is($voucher) && $i === $input && $s === 1)
        ->andReturn($execution);

    $recorder = Mockery::mock(WithdrawalPendingDisbursementRecorder::class);
    $recorder->shouldReceive('record')->never();

    $step = new ExecuteWithdrawalDisbursementStep($executor, $recorder);

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [],
        bankAccount: BankAccount::fromBankAccount('GXCHPHM2XXX:09173011987'),
        payoutRequest: $input,
        sliceNumber: 1,
    );

    $result = $step->handle($context, fn ($ctx) => $ctx);

    expect($result->disbursement)->toBe($execution);
});

it('records pending disbursement and rethrows on executor failure', function () {
    $voucher = issueVoucher();

    $input = PayoutRequestData::from([
        'reference' => $voucher->code.'-09173011987-S1',
        'amount' => 100.00,
        'account_number' => '09173011987',
        'bank_code' => 'GXCHPHM2XXX',
        'settlement_rail' => 'INSTAPAY',
    ]);

    $exception = new RuntimeException('Provider unavailable');

    $executor = Mockery::mock(WithdrawalDisbursementExecutor::class);
    $executor->shouldReceive('execute')
        ->once()
        ->andThrow($exception);

    $recorder = Mockery::mock(WithdrawalPendingDisbursementRecorder::class);
    $recorder->shouldReceive('record')
        ->once()
        ->with($voucher, $input, $exception);

    $step = new ExecuteWithdrawalDisbursementStep($executor, $recorder);

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [],
        bankAccount: BankAccount::fromBankAccount('GXCHPHM2XXX:09173011987'),
        payoutRequest: $input,
        sliceNumber: 1,
    );

    $step->handle($context, fn ($ctx) => $ctx);
})->throws(RuntimeException::class, 'Provider unavailable');
