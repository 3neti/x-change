<?php

declare(strict_types=1);

use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\XChange\Actions\Funding\RefreshFundingLiquidity;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use LBHurtado\XChange\Services\ProviderBalanceSnapshotStore;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

it('forces provider synchronization through the runtime provider adapter', function () {
    $operator = actingAsTestUser(0);
    $balances = Mockery::mock(BuildBalanceOverview::class);
    $balances->shouldReceive('handle')
        ->once()
        ->with($operator, 'paynamics', true, true)
        ->andReturn([
            'balances' => [
                [
                    'key' => 'provider_wallet',
                    'is_authoritative' => true,
                    'is_stale' => false,
                    'sync_status' => 'synced',
                ],
            ],
        ]);
    $connections = new TreasuryProviderConnectionCatalog([
        'paynamics-primary' => [
            'provider' => 'paynamics_constellation',
            'mode' => 'required',
            'currency' => 'PHP',
            'decimal_places' => 2,
            'inventory_reference' => 'inventory:paynamics:wallet-float',
            'settlement_resource_reference' => 'resource:paynamics:corporate-wallet',
            'settlement_resource_type' => 'emi_wallet_float',
            'custody_mode' => 'provider_projection',
            'required_capabilities' => [
                'balance_read',
            ],
        ],
    ]);

    $result = (new RefreshFundingLiquidity(
        $connections,
        $balances,
        fakeAuditLogger()->reset(),
        app(TreasuryInventoryPositionReadModelContract::class),
        app(ProviderBalanceSnapshotStore::class),
    ))->handle($operator);

    expect($result)
        ->refreshed->toBe(1)
        ->failed->toBe(0)
        ->busy->toBe(0)
        ->unavailable->toBe(0)
        ->and($result->connections)->toBe([
            [
                'provider' => 'paynamics_constellation',
                'status' => 'refreshed',
            ],
        ]);
});
