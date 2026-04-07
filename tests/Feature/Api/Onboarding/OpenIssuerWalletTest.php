<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Onboarding\OpenIssuerWallet;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\Onboarding\OpenIssuerWalletResultData;
use LBHurtado\XChange\Data\WalletData;

it('returns an opened issuer wallet via api', function () {
    $payload = [
        'issuer_id' => 1,
        'wallet' => [
            'slug' => 'platform',
            'name' => 'Platform Wallet',
        ],
        'metadata' => [],
    ];

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

    $response = $this->postJson(xchangeApi('onboarding/wallets'), $payload);

    $response
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'data' => $result->toArray(),
            'meta' => [],
        ]);
});

it('validates required payload fields for opening issuer wallet', function () {
    $response = $this->postJson(xchangeApi('onboarding/wallets'), []);

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'code' => 'VALIDATION_ERROR',
            'message' => 'The given data was invalid.',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'code',
            'errors' => [
                'issuer_id',
                'wallet',
            ],
        ]);
});
