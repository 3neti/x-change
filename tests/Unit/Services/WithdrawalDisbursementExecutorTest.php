<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Services\WithdrawalDisbursementExecutor;

it('executes disbursement and records reconciliation on success', function () {
    $voucher = issueVoucher();

    $input = PayoutRequestData::from([
        'reference' => $voucher->code.'-09173011987-S1',
        'amount' => 100.00,
        'account_number' => '09173011987',
        'bank_code' => 'GXCHPHM2XXX',
        'settlement_rail' => 'INSTAPAY',
    ]);

    $result = PayoutResultData::from([
        'uuid' => (string) Str::uuid(),
        'transaction_id' => 'TXN-123',
        'status' => PayoutStatus::PENDING,
        'provider' => 'netbank',
        'raw' => [],
    ]);

    $gateway = Mockery::mock(PayoutProvider::class);
    $gateway->shouldReceive('disburse')
        ->once()
        ->with($input)
        ->andReturn($result);

    $statusResolver = Mockery::mock(DisbursementStatusResolverContract::class);
    $statusResolver->shouldReceive('resolveFromGatewayResponse')
        ->once()
        ->with($result)
        ->andReturn('pending');

    $reconciliations = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $reconciliations->shouldReceive('record')
        ->twice()
        ->withArgs(fn (...$args) => true);

    $executor = new WithdrawalDisbursementExecutor(
        gateway: $gateway,
        reconciliations: $reconciliations,
        statusResolver: $statusResolver,
    );

    $execution = $executor->execute($voucher, $input, 1);

    expect($execution->response)->toBe($result)
        ->and($execution->status)->toBe('pending')
        ->and($execution->message)->toBeNull();
});

it('records reconciliation on provider failure', function () {
    $voucher = issueVoucher();

    $input = PayoutRequestData::from([
        'reference' => $voucher->code.'-09173011987-S1',
        'amount' => 100.00,
        'account_number' => '09173011987',
        'bank_code' => 'GXCHPHM2XXX',
        'settlement_rail' => 'INSTAPAY',
    ]);

    $exception = new RuntimeException('Provider unavailable');

    $gateway = Mockery::mock(PayoutProvider::class);
    $gateway->shouldReceive('disburse')
        ->once()
        ->with($input)
        ->andThrow($exception);

    $statusResolver = Mockery::mock(DisbursementStatusResolverContract::class);
    $statusResolver->shouldReceive('resolveFromGatewayException')
        ->once()
        ->with($exception)
        ->andReturn('failed');

    $reconciliations = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $reconciliations->shouldReceive('record')
        ->twice()
        ->withArgs(fn (...$args) => true);

    $executor = new WithdrawalDisbursementExecutor(
        gateway: $gateway,
        reconciliations: $reconciliations,
        statusResolver: $statusResolver,
    );

    expect(fn () => $executor->execute($voucher, $input, 1))
        ->toThrow(RuntimeException::class, 'Disbursement failed: Provider unavailable');
});

it('returns an explicit terminal provider rejection without requiring a transaction identifier', function () {
    $voucher = issueVoucher();
    $input = PayoutRequestData::from([
        'reference' => $voucher->code.'-09173011987-S1',
        'amount' => 100.00,
        'account_number' => '09173011987',
        'bank_code' => 'GXCHPHM2XXX',
        'settlement_rail' => 'INSTAPAY',
    ]);
    $result = new PayoutResultData(
        transaction_id: '',
        uuid: (string) Str::uuid(),
        status: PayoutStatus::FAILED,
        provider: 'netbank',
        metadata: ['failure_code' => 'invalid_destination'],
    );
    $gateway = Mockery::mock(PayoutProvider::class);
    $gateway->shouldReceive('disburse')->once()->with($input)->andReturn($result);
    $statusResolver = Mockery::mock(DisbursementStatusResolverContract::class);
    $statusResolver->shouldReceive('resolveFromGatewayResponse')
        ->once()
        ->with($result)
        ->andReturn('failed');
    $recorded = [];
    $reconciliations = Mockery::mock(DisbursementReconciliationStoreContract::class);
    $reconciliations->shouldReceive('record')
        ->twice()
        ->withArgs(function (array $attributes) use (&$recorded): bool {
            $recorded[] = $attributes;

            return true;
        });
    $executor = new WithdrawalDisbursementExecutor(
        gateway: $gateway,
        reconciliations: $reconciliations,
        statusResolver: $statusResolver,
    );

    $execution = $executor->execute($voucher, $input, 1);

    expect($execution->status)->toBe('failed')
        ->and($execution->response)->toBe($result)
        ->and($recorded[1]['provider_transaction_id'])->toBe('')
        ->and($recorded[1]['completed_at'])->not->toBeNull()
        ->and(data_get($recorded[1], 'meta.provider_response'))->toMatchArray([
            'received' => true,
            'status' => 'failed',
            'transaction_identifier_present' => false,
        ]);
});
