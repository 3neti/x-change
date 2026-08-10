<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Claim\PayoutDestinationRegistry;

it('builds a masked GCash payout route snapshot', function (): void {
    $snapshot = app(PayoutDestinationRegistry::class)->snapshot(
        bankCode: 'GXCHPHM2XXX',
        accountNumber: '09173011987',
        settlementRail: 'INSTAPAY',
    );

    expect($snapshot)->toMatchArray([
        'bank_code' => 'GXCHPHM2XXX',
        'bank_name' => 'GCash',
        'bank_label' => 'GCash',
        'provider_icon_key' => 'wallet.gcash',
        'settlement_rail' => 'INSTAPAY',
        'account_number_masked' => '*******1987',
        'route' => ['x-change', 'NetBank', 'InstaPay', 'GCash', '*******1987'],
    ]);
});

it('keeps Maya Wallet and Maya Bank distinct', function (): void {
    $registry = app(PayoutDestinationRegistry::class);

    expect($registry->institution('PAPHPHM1XXX'))->toMatchArray([
        'label' => 'Maya Wallet',
        'short_label' => 'Maya Wallet',
        'category' => 'wallet',
    ])->and($registry->institution('MYDBPHM2XXX'))->toMatchArray([
        'label' => 'Maya Bank',
        'short_label' => 'Maya Bank',
        'category' => 'bank',
    ]);
});

it('reads the configurable default destination from x-change config', function (): void {
    config()->set('x-change.claim.destination.default_bank_code', 'PAPHPHM1XXX');
    config()->set('x-change.claim.destination.default_settlement_rail', 'INSTAPAY');

    $registry = app(PayoutDestinationRegistry::class);

    expect($registry->defaultBankCode())->toBe('PAPHPHM1XXX')
        ->and($registry->defaultSettlementRail())->toBe('INSTAPAY');
});
