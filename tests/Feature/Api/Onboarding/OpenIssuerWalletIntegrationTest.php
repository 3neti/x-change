<?php

declare(strict_types=1);

use LBHurtado\XChange\Tests\Fakes\User;

it('opens an issuer wallet end to end via api', function () {
    config()->set('x-change.treasury.legal_profile', 'treasury-settlement-ph-v1');
    config()->set('x-change.treasury.legal_profile_version', '2026-07-24.1');

    $issuer = User::query()->create([
        'name' => 'Issuer Name',
        'email' => 'issuer@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($issuer);

    $payload = validOpenIssuerWalletPayload($issuer->id);

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
