<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\Voucher\Services\VoucherSlicePlanFactory;
use LBHurtado\XChange\Data\WithdrawalDisbursementExecutionData;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Enums\VoucherSliceExecutionStatus;
use LBHurtado\XChange\Services\Slices\VoucherSliceExecutionCoordinator;
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
    $sliceExecutions = app(VoucherSliceExecutionCoordinator::class);

    $step = new ExecuteWithdrawalDisbursementStep($executor, $recorder, $sliceExecutions);

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
    $sliceExecutions = app(VoucherSliceExecutionCoordinator::class);

    $step = new ExecuteWithdrawalDisbursementStep($executor, $recorder, $sliceExecutions);

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [],
        bankAccount: BankAccount::fromBankAccount('GXCHPHM2XXX:09173011987'),
        payoutRequest: $input,
        sliceNumber: 1,
    );

    $step->handle($context, fn ($ctx) => $ctx);
})->throws(RuntimeException::class, 'Provider unavailable');

it('crosses the slice execution boundary immediately before disbursement', function () {
    $voucher = issueVoucher();
    $metadata = (array) $voucher->metadata;
    data_set(
        $metadata,
        'instructions.slice_plan',
        app(VoucherSlicePlanFactory::class)->equal(10_000, 'PHP', 2)->canonicalArray(),
    );
    $voucher->forceFill(['metadata' => $metadata])->save();

    $sliceExecutions = app(VoucherSliceExecutionCoordinator::class);
    $reservation = $sliceExecutions->reserve($voucher, [
        '_meta' => ['idempotency_key' => 'pipeline-provider-boundary'],
    ]);
    $input = PayoutRequestData::from([
        'reference' => $reservation->execution->provider_operation_reference,
        'amount' => 50.00,
        'account_number' => '09173011987',
        'bank_code' => 'GXCHPHM2XXX',
        'settlement_rail' => 'INSTAPAY',
    ]);
    $execution = new WithdrawalDisbursementExecutionData(
        input: $input,
        response: PayoutResultData::from([
            'uuid' => (string) Str::uuid(),
            'transaction_id' => 'TXN-SLICE',
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
        ->andReturnUsing(function () use ($reservation, $execution) {
            expect($reservation->execution->fresh()->status)
                ->toBe(VoucherSliceExecutionStatus::Executing);

            return $execution;
        });
    $recorder = Mockery::mock(WithdrawalPendingDisbursementRecorder::class);
    $recorder->shouldReceive('record')->never();
    $step = new ExecuteWithdrawalDisbursementStep($executor, $recorder, $sliceExecutions);
    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: $reservation->payload,
        bankAccount: BankAccount::fromBankAccount('GXCHPHM2XXX:09173011987'),
        payoutRequest: $input,
        sliceNumber: 1,
    );

    $step->handle($context, fn ($resolved) => $resolved);
});
