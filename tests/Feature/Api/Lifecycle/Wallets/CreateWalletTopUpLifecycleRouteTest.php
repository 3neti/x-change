<?php

declare(strict_types=1);

it('rejects direct wallet top ups and points callers to Funding Intents', function () {
    $payload = [
        'amount' => 500.00,
        'currency' => 'PHP',
        'reference' => 'TOPUP-002',
        'metadata' => [],
    ];

    $response = $this->postJson('/api/x/v1/wallets/platform/top-ups', $payload);

    $response
        ->assertGone()
        ->assertJson([
            'success' => false,
            'code' => 'DIRECT_TOP_UP_DISABLED',
            'errors' => [
                'wallet' => 'platform',
                'replacement' => '/api/x/v1/funding-intents',
            ],
        ]);
});

it('does not expose validation as a way around the disabled direct top up boundary', function () {
    $response = $this->postJson('/api/x/v1/wallets/platform/top-ups', []);

    $response
        ->assertGone()
        ->assertJson([
            'success' => false,
            'code' => 'DIRECT_TOP_UP_DISABLED',
        ]);
});
