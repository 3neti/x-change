<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Onboarding\OpenIssuerWallet;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\Onboarding\OpenIssuerWalletResultData;
use LBHurtado\XChange\Data\WalletData;

it('opens an issuer wallet through the lifecycle route surface', function () {
    $payload = validOpenIssuerWalletPayload();

    $result = new OpenIssuerWalletResultData(
        issuer: new IssuerData(
            id: 1,
        ),
        wallet: new WalletData(
            id: 10,
            slug: 'platform',
            name: 'Platform Wallet',
            balance: 0,
        ),
    );

    $action = Mockery::mock(OpenIssuerWallet::class);
    $action->shouldReceive('handle')
        ->once()
        ->with($payload)
        ->andReturn($result);

    $this->app->instance(OpenIssuerWallet::class, $action);

    $response = $this->postJson('/api/x/v1/issuers/1/wallets', $payload);

    $response
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'data' => [
                'issuer' => [
                    'id' => 1,
                ],
                'wallet' => [
                    'id' => 10,
                    'slug' => 'platform',
                    'name' => 'Platform Wallet',
                    'balance' => 0,
                ],
            ],
            'meta' => [],
        ]);
});
