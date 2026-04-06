<?php

declare(strict_types=1);

use LBHurtado\XChange\Tests\Fakes\User;

it('opens an issuer wallet end to end via api', function () {
    $issuer = User::query()->create([
        'name' => 'Issuer Name',
        'email' => 'issuer@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($issuer);

    $payload = [
        'issuer_id' => $issuer->id,
        'wallet' => [
            'slug' => 'platform',
            'name' => 'Platform Wallet',
        ],
        'metadata' => [],
    ];

    $response = $this->postJson(xchangeApi('onboarding/wallets'), $payload);

    $response
        ->assertCreated()
        ->assertJsonStructure([
            'success',
            'data' => [
                'issuer' => [
                    'id',
                ],
                'wallet' => [
                    'id',
                    'slug',
                    'name',
                    'balance',
                ],
            ],
            'meta',
        ]);

    expect($response->json('data.issuer.id'))->toBe($issuer->id);
    expect($response->json('data.wallet.slug'))->toBe('platform');
});
