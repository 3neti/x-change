<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\XChange\Services\WithdrawalRailGuard;

it('allows instapay disbursement to emi', function () {
    $bankRegistry = Mockery::mock(BankRegistry::class);

    $bankRegistry->shouldReceive('isEMI')
        ->never();

    $guard = new WithdrawalRailGuard($bankRegistry);

    $guard->assertAllowed(PayoutRequestData::from([
        'reference' => 'REF-001',
        'amount' => 100.00,
        'account_number' => '09173011987',
        'bank_code' => 'GXCHPHM2XXX',
        'settlement_rail' => 'INSTAPAY',
    ]));

    expect(true)->toBeTrue();
});

it('allows pesonet disbursement to non emi', function () {
    $bankRegistry = Mockery::mock(BankRegistry::class);

    $bankRegistry->shouldReceive('isEMI')
        ->once()
        ->with('BNORPHMMXXX')
        ->andReturn(false);

    $bankRegistry->shouldReceive('getBankName')
        ->never();

    $guard = new WithdrawalRailGuard($bankRegistry);

    $guard->assertAllowed(PayoutRequestData::from([
        'reference' => 'REF-002',
        'amount' => 50000.00,
        'account_number' => '1234567890',
        'bank_code' => 'BNORPHMMXXX',
        'settlement_rail' => 'PESONET',
    ]));

    expect(true)->toBeTrue();
});

it('blocks pesonet disbursement to emi', function () {
    $bankRegistry = Mockery::mock(BankRegistry::class);

    $bankRegistry->shouldReceive('isEMI')
        ->once()
        ->with('GXCHPHM2XXX')
        ->andReturn(true);

    $bankRegistry->shouldReceive('getBankName')
        ->once()
        ->with('GXCHPHM2XXX')
        ->andReturn('GCash');

    $guard = new WithdrawalRailGuard($bankRegistry);

    $guard->assertAllowed(PayoutRequestData::from([
        'reference' => 'REF-003',
        'amount' => 50000.00,
        'account_number' => '09173011987',
        'bank_code' => 'GXCHPHM2XXX',
        'settlement_rail' => 'PESONET',
    ]));
})->throws(
    RuntimeException::class,
    'Cannot disburse to GCash via PESONET. E-money institutions require INSTAPAY.'
);
