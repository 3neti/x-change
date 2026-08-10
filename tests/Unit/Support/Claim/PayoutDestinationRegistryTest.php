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

it('resolves icon assets for known institutions, rails, and the orchestrator', function (): void {
    $registry = app(PayoutDestinationRegistry::class);

    $snapshot = $registry->snapshot(
        bankCode: 'GXCHPHM2XXX',
        accountNumber: '09173011987',
        settlementRail: 'INSTAPAY',
    );

    expect($snapshot['icon_asset'])->toBe('/vendor/x-change/images/payout-destinations/gcash-128.png')
        ->and($snapshot['route_icons'])->toHaveCount(4)
        ->and($snapshot['route_icons'][0])->toBe('/vendor/x-change/images/payout-destinations/x-change-128.png')
        ->and($snapshot['route_icons'][3])->toBe('/vendor/x-change/images/payout-destinations/gcash-128.png');

    expect($registry->institution('UNKNOWNBANKCODE')['icon_asset'])->toBeNull();
});

it('reads the configurable default destination from x-change config', function (): void {
    config()->set('x-change.claim.destination.default_bank_code', 'PAPHPHM1XXX');
    config()->set('x-change.claim.destination.default_settlement_rail', 'INSTAPAY');

    $registry = app(PayoutDestinationRegistry::class);

    expect($registry->defaultBankCode())->toBe('PAPHPHM1XXX')
        ->and($registry->defaultSettlementRail())->toBe('INSTAPAY');
});
