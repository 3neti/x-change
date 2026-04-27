<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeReadinessData;
use LBHurtado\XChange\Services\DefaultSettlementExecutionService;

it('returns pending settlement execution result when envelope is ready', function () {
    $voucher = new Voucher;
    $voucher->forceFill([
        'code' => 'SETTLE-1234',
        'voucher_type' => 'settlement',
    ]);

    $envelope = Mockery::mock(SettlementEnvelopeReadinessContract::class);
    $envelope->shouldReceive('check')
        ->once()
        ->with($voucher)
        ->andReturn(new SettlementEnvelopeReadinessData(
            required: true,
            exists: true,
            ready: true,
            missing: [],
            meta: [
                'driver' => 'fake',
                'envelope_id' => 'ENV-123',
            ],
        ));

    $service = new DefaultSettlementExecutionService($envelope);

    $result = $service->execute($voucher, [
        'mobile' => '639171234567',
        'amount' => 1000,
    ]);

    expect($result->voucher_code)->toBe('SETTLE-1234');
    expect($result->status)->toBe('pending');
    expect($result->message)->toBe('Settlement execution is pending.');
    expect($result->meta)->toMatchArray([
        'settlement_mode' => 'stub',
        'envelope_id' => 'ENV-123',
    ]);
});

it('blocks settlement execution when envelope is not ready', function () {
    $voucher = new Voucher;
    $voucher->forceFill([
        'code' => 'SETTLE-1234',
        'voucher_type' => 'settlement',
    ]);

    $envelope = Mockery::mock(SettlementEnvelopeReadinessContract::class);
    $envelope->shouldReceive('check')
        ->once()
        ->with($voucher)
        ->andReturn(SettlementEnvelopeReadinessData::notAvailable(required: true));

    $service = new DefaultSettlementExecutionService($envelope);

    $result = $service->execute($voucher, []);

    expect($result->voucher_code)->toBe('SETTLE-1234');
    expect($result->status)->toBe('blocked');
    expect($result->message)->toBe('Settlement envelope is not ready.');
    expect($result->meta)->toMatchArray([
        'missing' => ['settlement_envelope'],
    ]);
});
