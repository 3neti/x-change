<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Contracts\SettlementEnvelopeExecutionGateway;
use LBHurtado\Voucher\Contracts\StoredValueExecutionGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;
use LBHurtado\Voucher\Services\ExecutionDriverRegistry;
use LBHurtado\XChange\Contracts\Execution\StoredValueDestinationAuthorityContract;
use LBHurtado\XChange\Services\Execution\UnavailableStoredValueDestinationAuthority;
use LBHurtado\XChange\Services\Execution\XChangeLiveCashExecutionDriver;
use LBHurtado\XChange\Services\Execution\XChangeSettlementEnvelopeExecutionGateway;
use LBHurtado\XChange\Services\Execution\XChangeStoredValueExecutionGateway;

it('binds voucher settlement envelope execution gateway to the x-change adapter', function () {
    expect(app(SettlementEnvelopeExecutionGateway::class))
        ->toBeInstanceOf(XChangeSettlementEnvelopeExecutionGateway::class);
});

it('binds voucher stored value execution gateway to the x-change adapter singleton', function () {
    $first = app(StoredValueExecutionGateway::class);
    $second = app(StoredValueExecutionGateway::class);

    expect($first)
        ->toBeInstanceOf(XChangeStoredValueExecutionGateway::class)
        ->and($second)->toBe($first);
});

it('binds stored value destination authority to a fail closed default', function () {
    $authority = app(StoredValueDestinationAuthorityContract::class);

    expect($authority)
        ->toBeInstanceOf(UnavailableStoredValueDestinationAuthority::class)
        ->and($authority->isReady())->toBeFalse()
        ->and(fn () => $authority->authorize(
            new ExecutionContextData(
                contact: new Contact,
                voucherCode: 'TEST',
            ),
            100,
        ))->toThrow(
            StoredValueSpendRejectedException::class,
            'Stored value destination authority has not been commissioned.',
        );
});

it('registers the x-change live cash execution driver with the voucher registry', function () {
    $registry = app(ExecutionDriverRegistry::class);

    expect($registry->has('x_change_live_cash'))->toBeTrue()
        ->and($registry->resolve('x_change_live_cash'))
        ->toBeInstanceOf(XChangeLiveCashExecutionDriver::class);
});
