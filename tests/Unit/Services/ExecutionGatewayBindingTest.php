<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Contracts\PayableCollectionExecutionGateway;
use LBHurtado\Voucher\Contracts\SettlementEnvelopeExecutionGateway;
use LBHurtado\Voucher\Contracts\StoredValueExecutionGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;
use LBHurtado\Voucher\Services\ExecutionDriverRegistry;
use LBHurtado\XChange\Contracts\Execution\StoredValueDestinationAuthorityContract;
use LBHurtado\XChange\Contracts\Execution\StoredValueHolderAuthorityContract;
use LBHurtado\XChange\Services\Execution\AuthenticatedStoredValueHolderAuthority;
use LBHurtado\XChange\Services\Execution\PartnerApiStoredValueDestinationAuthority;
use LBHurtado\XChange\Services\Execution\WalletPayableCollectionExecutionGateway;
use LBHurtado\XChange\Services\Execution\WalletStoredValueExecutionGateway;
use LBHurtado\XChange\Services\Execution\XChangeLiveCashExecutionDriver;
use LBHurtado\XChange\Services\Execution\XChangeSettlementEnvelopeExecutionGateway;

it('binds voucher settlement envelope execution gateway to the x-change adapter', function () {
    expect(app(SettlementEnvelopeExecutionGateway::class))
        ->toBeInstanceOf(XChangeSettlementEnvelopeExecutionGateway::class);
});

it('binds voucher payable collection execution gateway to the x-change adapter', function () {
    expect(app(PayableCollectionExecutionGateway::class))
        ->toBeInstanceOf(WalletPayableCollectionExecutionGateway::class);
});

it('binds voucher stored value execution gateway to the scoped x-change adapter', function () {
    $first = app(StoredValueExecutionGateway::class);
    $second = app(StoredValueExecutionGateway::class);

    expect($first)
        ->toBeInstanceOf(WalletStoredValueExecutionGateway::class)
        ->and($second)->toBe($first);
});

it('binds stored value holder authority to verified authenticated Accounts', function () {
    $authority = app(StoredValueHolderAuthorityContract::class);

    expect($authority)
        ->toBeInstanceOf(AuthenticatedStoredValueHolderAuthority::class)
        ->and($authority->isReady())->toBeTrue()
        ->and(fn () => $authority->authorize(
            new ExecutionContextData(
                contact: new Contact,
                voucherCode: 'TEST',
            ),
        ))->toThrow(
            StoredValueSpendRejectedException::class,
            'requires an authenticated Account holder',
        );
});

it('memoizes stored value holder readiness schema checks within the scoped authority', function () {
    $authority = app(StoredValueHolderAuthorityContract::class);
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        if (
            str_contains($query->sql, 'pragma_table_xinfo')
            || str_contains($query->sql, 'information_schema.columns')
        ) {
            $queries[] = $query->sql;
        }
    });

    $first = $authority->isReady();
    $second = $authority->isReady();

    expect($second)->toBe($first)
        ->and($queries)->toHaveCount(1);
});

it('binds stored value destination authority to the fail closed Partner API mandate', function () {
    $authority = app(StoredValueDestinationAuthorityContract::class);

    expect($authority)
        ->toBeInstanceOf(PartnerApiStoredValueDestinationAuthority::class)
        ->and($authority->isReady())->toBeFalse()
        ->and(fn () => $authority->authorize(
            new ExecutionContextData(
                contact: new Contact,
                voucherCode: 'TEST',
            ),
            100,
        ))->toThrow(
            StoredValueSpendRejectedException::class,
            'unavailable outside an authenticated Partner API request',
        );
});

it('registers the x-change live cash execution driver with the voucher registry', function () {
    $registry = app(ExecutionDriverRegistry::class);

    expect($registry->has('x_change_live_cash'))->toBeTrue()
        ->and($registry->resolve('x_change_live_cash'))
        ->toBeInstanceOf(XChangeLiveCashExecutionDriver::class);
});
