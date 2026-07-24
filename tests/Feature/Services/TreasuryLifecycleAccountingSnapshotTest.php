<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Services\Treasury\TreasuryLifecycleAccountingSnapshot;
use LBHurtado\XChange\Services\Treasury\TreasuryOpeningBalanceReconciliationService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

it('exposes provider, system, account, legacy, and liability accounting without secrets', function () {
    enableNetbankTreasuryForTests();
    config()->set('x-change.treasury.simulator.enabled', true);
    config()->set('x-change.treasury.connections.paynamics-primary', [
        'provider' => 'paynamics_constellation',
        'mode' => 'disabled',
        'currency' => 'PHP',
        'decimal_places' => 2,
        'inventory_reference' => 'inventory:paynamics:wallet-float',
        'settlement_resource_reference' => 'resource:paynamics:corporate-wallet',
        'settlement_resource_type' => 'emi_wallet_float',
        'custody_mode' => 'provider_projection',
        'required_capabilities' => [],
    ]);
    app()->forgetInstance(TreasuryProviderConnectionCatalog::class);

    $accountOwner = actingAsTestUser(12_345);
    app(TreasuryAccountPortfolioProvisioningContract::class)
        ->provision($accountOwner, ['netbank-primary']);
    $observation = app(TreasuryOpeningBalanceReconciliationService::class)
        ->simulateDeposit('netbank-primary', 1_000_000_00, 'SNAPSHOT-DEPOSIT-1');

    $snapshot = app(TreasuryLifecycleAccountingSnapshot::class)
        ->capture($accountOwner, [$observation]);
    $netbank = collect($snapshot['connections'])
        ->firstWhere('reference', 'netbank-primary');
    $paynamics = collect($snapshot['connections'])
        ->firstWhere('reference', 'paynamics-primary');
    $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR);

    expect($snapshot['account']['legacy_compatibility_balance_minor'])->toBe(12_345)
        ->and($snapshot['account']['internal_balance_minor'])->toBe(0)
        ->and($netbank['provider_observation']['balance_minor'])->toBe(1_000_000_00)
        ->and($netbank['inventory']['balance_minor'])->toBe(1_000_000_00)
        ->and($netbank['system_positions']['by_purpose']['legacy_unattributed'])
        ->toBe(1_000_000_00)
        ->and($netbank['account_positions']['status'])->toBe('provisioned')
        ->and($netbank['account_positions']['balance_minor'])->toBe(0)
        ->and($netbank['control']['inventory_equals_positions'])->toBeTrue()
        ->and($paynamics['active'])->toBeFalse()
        ->and($paynamics['account_positions']['status'])->toBe('not_provisioned')
        ->and($paynamics['account_positions']['balance_minor'])->toBeNull()
        ->and($encoded)->not->toContain('password')
        ->and($encoded)->not->toContain('account_number')
        ->and($encoded)->not->toContain('mobile');
});
