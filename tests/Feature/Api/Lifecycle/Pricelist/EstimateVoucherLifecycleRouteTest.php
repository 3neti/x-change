<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Data\PricingEstimateData;

it('returns a voucher estimate via the lifecycle route surface', function () {
    $payload = [
        'cash' => [
            'amount' => 100.0,
            'currency' => 'PHP',
            'settlement_rail' => 'INSTAPAY',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'email' => 'example@example.com',
            'mobile' => '09171234567',
            'webhook' => 'https://example.com/webhook',
        ],
        'rider' => [
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
        'metadata' => [],
    ];

    $result = new PricingEstimateData(
        currency: 'PHP',
        base_fee: 0.0,
        components: [],
        total: 0.0,
    );

    $action = Mockery::mock(EstimatePayCodeCost::class);
    $action->shouldReceive('handle')
        ->once()
        ->with($payload)
        ->andReturn($result);

    $this->app->instance(EstimatePayCodeCost::class, $action);

    $response = $this->postJson('/api/x/v1/vouchers/estimate', $payload);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'currency',
                'base_fee',
                'components',
                'total',
            ],
            'meta',
        ])
        ->assertJson([
            'success' => true,
            'data' => [
                'currency' => 'PHP',
                'base_fee' => 0.0,
                'components' => [],
                'total' => 0.0,
            ],
            'meta' => [],
        ]);
});

it('validates required pricing payload fields through the lifecycle route surface', function () {
    $response = $this->postJson('/api/x/v1/vouchers/estimate', []);

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
                'cash',
                'inputs',
                'feedback',
                'rider',
            ],
        ]);
});
