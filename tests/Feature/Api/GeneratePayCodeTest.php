<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;

it('returns a generated pay code via api', function () {
    $payload = [
        'cash' => [
            'amount' => 100.0,
            'currency' => 'PHP',
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
    ];

    $result = [
        'voucher_id' => 99,
        'code' => 'TEST-1234',
        'amount' => 100.0,
        'currency' => 'PHP',
        'cost' => [
            'currency' => 'PHP',
            'base_fee' => 1.0,
            'components' => [],
            'total' => 1.0,
        ],
        'wallet' => [
            'balance_before' => 1000.0,
            'balance_after' => 999.0,
        ],
        'debit' => [
            'id' => 501,
        ],
    ];

    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldReceive('handle')
        ->once()
        ->with($payload)
        ->andReturn($result);

    $this->app->instance(GeneratePayCode::class, $action);

    $response = $this->postJson('/pay-codes', $payload);

    $response
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'data' => $result,
            'meta' => [],
        ]);
});

it('validates required payload fields for pay code generation', function () {
    $response = $this->postJson('/pay-codes', []);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'cash',
            'inputs',
            'feedback',
            'rider',
        ]);
});
