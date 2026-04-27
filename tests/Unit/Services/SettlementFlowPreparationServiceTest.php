<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SettlementFlowPreparationContract;

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
    expect($result->entry_route)->toBe('settle');
    expect($result->requires_envelope)->toBeTrue();
    expect($result->requirements)->toMatchArray([
        'envelope' => true,
    ]);
    expect($result->capabilities)->toMatchArray([
        'can_disburse' => true,
        'can_collect' => true,
        'can_settle' => true,
    ]);
    expect($result->messages)->toContain('Settlement preparation is not yet implemented.');
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
