<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\XChange\Contracts\SettlementRailCapabilityRegistryContract;

beforeEach(function (): void {
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    config()->set('x-change.provider_runtime.providers.netbank.enabled', true);
    config()->set('omnipay.gateways.netbank.options.rails', [
        'INSTAPAY' => [
            'enabled' => true,
            'min_amount' => 1,
            'max_amount' => 5_000_000,
            'fee' => 1_000,
        ],
        'PESONET' => [
            'enabled' => true,
            'min_amount' => 1,
            'max_amount' => 100_000_000,
            'fee' => 2_500,
        ],
    ]);
});

it('exposes sanitized configured rail capabilities without provider calls', function (): void {
    $capabilities = app(SettlementRailCapabilityRegistryContract::class)->sanitized();

    expect($capabilities)->toMatchArray([
        'schema' => 'x-change.cockpit.settlement-rail-capabilities.v1',
        'provider' => [
            'code' => 'netbank',
            'label' => 'NetBank',
            'enabled' => true,
            'binding_provider' => 'netbank',
            'binding_coherent' => true,
        ],
        'default_mode' => 'automatic',
        'automatic_policy' => [
            'instapay_below_amount_minor' => 5_000_000,
            'resolved_per_payout' => true,
        ],
        'live_provider_call' => false,
    ])->and($capabilities['rails'])->toHaveCount(2)
        ->and($capabilities['rails'][0])->toMatchArray([
            'code' => 'INSTAPAY',
            'label' => 'InstaPay',
            'enabled' => true,
            'maximum_amount_minor' => 5_000_000,
            'provider_fee_minor' => 1_000,
        ])
        ->and($capabilities)->not->toHaveKeys([
            'credentials',
            'source_account_number',
            'raw_provider_configuration',
        ]);
});

it('fails closed when readiness and payout adapter providers diverge', function (): void {
    config()->set('x-change.payout.provider', 'paynamics');

    $capabilities = app(SettlementRailCapabilityRegistryContract::class)->sanitized();

    expect($capabilities['provider'])->toMatchArray([
        'code' => 'netbank',
        'binding_provider' => 'paynamics',
        'binding_coherent' => false,
    ])->and($capabilities['rails'][0]['enabled'])->toBeFalse()
        ->and($capabilities['rails'][0]['availability_reason'])
        ->toContain('readiness resolves to NetBank')
        ->toContain('payout adapter resolves to Paynamics');
});

it('rejects disabled and out-of-range rails before provider dispatch', function (): void {
    $registry = app(SettlementRailCapabilityRegistryContract::class);

    expect(fn () => $registry->assertSupports(PayoutRequestData::from([
        'reference' => 'rail-limit-01',
        'amount' => 50_000.01,
        'account_number' => '001234567890',
        'bank_code' => 'BNORPHMMXXX',
        'settlement_rail' => SettlementRail::INSTAPAY->value,
    ])))->toThrow(RuntimeException::class, 'InstaPay permits at most PHP 50000.00.');

    config()->set('omnipay.gateways.netbank.options.rails.PESONET.enabled', false);

    expect(fn () => $registry->assertSupports(PayoutRequestData::from([
        'reference' => 'rail-disabled-01',
        'amount' => 50_000.00,
        'account_number' => '001234567890',
        'bank_code' => 'BNORPHMMXXX',
        'settlement_rail' => SettlementRail::PESONET->value,
    ])))->toThrow(RuntimeException::class, 'NetBank is disabled for PESONET.');
});
