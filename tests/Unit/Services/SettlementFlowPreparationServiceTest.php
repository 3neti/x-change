<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Contracts\SettlementFlowPreparationContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeReadinessData;
use LBHurtado\XChange\Services\DefaultSettlementFlowPreparationService;

it('prepares a settlement flow stub for settlement vouchers', function () {
    $voucher = new Voucher;
    $voucher->code = 'SETTLE-1234';
    $voucher->setAttribute('metadata', [
        'flow_type' => 'settlement',
    ]);

    $service = app(SettlementFlowPreparationContract::class);

    $result = $service->prepare($voucher);

    expect($result->voucher_code)->toBe('SETTLE-1234');
    expect($result->can_start)->toBeFalse();
    expect($result->requires_envelope)->toBeTrue();

    expect($result->envelope->required)->toBeTrue();
    expect($result->envelope->exists)->toBeFalse();
    expect($result->envelope->ready)->toBeFalse();
    expect($result->envelope->missing)->toBe(['settlement_envelope']);

    expect($result->entry_route)->toBe('settle');
    expect($result->requires_envelope)->toBeTrue();
    expect($result->requirements)->toMatchArray([
        'envelope' => true,
        'missing' => ['settlement_envelope'],
    ]);
    expect($result->capabilities)->toMatchArray([
        'can_disburse' => true,
        'can_collect' => true,
        'can_settle' => true,
    ]);
    expect($result->messages)->toContain('Settlement envelope is not ready.');
});

it('rejects non-settlement vouchers from settlement preparation', function () {
    $voucher = new Voucher;
    $voucher->code = 'CASH-1234';
    $voucher->setAttribute('metadata', [
        'flow_type' => 'disbursable',
    ]);

    $service = app(SettlementFlowPreparationContract::class);

    expect(fn () => $service->prepare($voucher))
        ->toThrow(RuntimeException::class, 'cannot prepare settlement flow');
});

it('allows settlement preparation to start when required envelope is ready', function () {
    $voucher = new Voucher;
    $voucher->code = 'SETTLE-READY';
    $voucher->forceFill([
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

    $service = new DefaultSettlementFlowPreparationService(
        app(VoucherFlowCapabilityResolverContract::class),
        $envelope,
    );

    $result = $service->prepare($voucher);

    expect($result->can_start)->toBeTrue();
    expect($result->envelope->ready)->toBeTrue();
    expect($result->messages)->toBe([]);
});
