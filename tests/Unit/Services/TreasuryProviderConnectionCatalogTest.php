<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\XChange\Enums\TreasuryConnectionMode;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

it('selects only explicitly active treasury provider connections', function () {
    $catalog = new TreasuryProviderConnectionCatalog([
        'future-primary' => [
            'provider' => 'future_emi',
            'mode' => 'optional',
            'currency' => 'php',
            'decimal_places' => 2,
            'inventory_reference' => 'inventory:future:primary:php',
            'settlement_resource_reference' => 'resource:future:primary:php',
            'settlement_resource_type' => 'regulated_stored_value',
            'custody_mode' => 'provider_projection',
            'required_capabilities' => [
                'readiness_probe',
                'funding_evidence_read',
            ],
        ],
        'netbank-disabled' => [
            'provider' => 'netbank',
            'mode' => 'disabled',
            'currency' => 'PHP',
            'decimal_places' => 2,
            'inventory_reference' => 'inventory:netbank:primary:php',
            'settlement_resource_reference' => 'resource:netbank:primary:php',
            'settlement_resource_type' => 'cash_at_bank',
            'custody_mode' => 'provider_projection',
            'required_capabilities' => [],
        ],
    ]);

    $connections = $catalog->active();

    expect($connections)->toHaveCount(1)
        ->and($connections[0]->reference)->toBe('future-primary')
        ->and($connections[0]->provider)->toBe('future_emi')
        ->and($connections[0]->mode)->toBe(TreasuryConnectionMode::Optional)
        ->and($connections[0]->currency)->toBe('PHP')
        ->and($connections[0]->requiredCapabilities)->toBe([
            ProviderCapability::ReadinessProbe,
            ProviderCapability::FundingEvidenceRead,
        ]);
});

it('rejects unknown or disabled connection selection', function () {
    $catalog = new TreasuryProviderConnectionCatalog([
        'netbank-primary' => [
            'provider' => 'netbank',
            'mode' => 'disabled',
            'currency' => 'PHP',
            'decimal_places' => 2,
            'inventory_reference' => 'inventory:netbank:primary:php',
            'settlement_resource_reference' => 'resource:netbank:primary:php',
            'settlement_resource_type' => 'cash_at_bank',
            'custody_mode' => 'provider_projection',
            'required_capabilities' => [],
        ],
    ]);

    expect(fn () => $catalog->active(['netbank-primary']))
        ->toThrow(
            TreasuryConfigurationException::class,
            'Unknown or disabled Treasury connections: netbank-primary.',
        );
});

it('fails closed for invalid monetary and capability configuration', function (
    array $overrides,
    string $message,
) {
    $configuration = array_replace([
        'provider' => 'future_emi',
        'mode' => 'required',
        'currency' => 'PHP',
        'decimal_places' => 2,
        'inventory_reference' => 'inventory:future:primary:php',
        'settlement_resource_reference' => 'resource:future:primary:php',
        'settlement_resource_type' => 'regulated_stored_value',
        'custody_mode' => 'provider_projection',
        'required_capabilities' => ['readiness_probe'],
    ], $overrides);

    expect(fn () => (new TreasuryProviderConnectionCatalog([
        'future-primary' => $configuration,
    ]))->all())->toThrow(TreasuryConfigurationException::class, $message);
})->with([
    'decimal places' => [
        ['decimal_places' => 7],
        'Treasury connection [future-primary] has invalid decimal places.',
    ],
    'capability' => [
        ['required_capabilities' => ['invent_money']],
        'Treasury connection [future-primary] declares an unknown capability.',
    ],
]);
