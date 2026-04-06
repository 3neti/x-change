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
        'issuer' => [
            'id' => 1,
        ],
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
            'amount' => null,
        ],
        'links' => [
            'redeem' => 'https://example.test/disburse?code=TEST-1234',
            'redeem_path' => '/disburse?code=TEST-1234',
        ],
    ];

    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldReceive('handle')
        ->once()
        ->with(Mockery::on(function (array $actual) use ($payload) {
            expect((float) data_get($actual, 'cash.amount'))->toBe(100.0);
            expect(data_get($actual, 'cash.currency'))->toBe('PHP');

            expect(data_get($actual, 'inputs.fields'))->toBe([]);

            expect(data_get($actual, 'feedback.email'))->toBe('example@example.com');
            expect(data_get($actual, 'feedback.mobile'))->toBe('09171234567');
            expect(data_get($actual, 'feedback.webhook'))->toBe('https://example.com/webhook');


            expect(data_get($actual, 'rider.message'))->toBeNull();
            expect(data_get($actual, 'rider.url'))->toBeNull();
            expect(data_get($actual, 'rider.redirect_timeout'))->toBeNull();
            expect(data_get($actual, 'rider.splash'))->toBeNull();
            expect(data_get($actual, 'rider.splash_timeout'))->toBeNull();
            expect(data_get($actual, 'rider.og_source'))->toBeNull();

            expect($actual)->toHaveKey('_meta');
            expect(data_get($actual, '_meta.idempotency_key'))->toBeNull();
            expect(data_get($actual, '_meta.correlation_id'))->toBeNull();

            return true;
        }))
        ->andReturn($result);

    $this->app->instance(GeneratePayCode::class, $action);

    $response = $this->postJson(xchangeApi('pay-codes'), $payload);

    $response
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'data' => $result,
            'meta' => [
                'idempotency' => [
                    'key' => null,
                    'replayed' => false,
                ],
            ],
        ]);
});

it('validates required payload fields for pay code generation', function () {
    $response = $this->postJson(xchangeApi('pay-codes'), []);

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
