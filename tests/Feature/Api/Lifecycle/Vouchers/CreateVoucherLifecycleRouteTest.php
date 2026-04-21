<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;

it('returns a generated voucher via the lifecycle route surface', function () {
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

    $result = new GeneratePayCodeResultData(
        voucher_id: 99,
        code: 'TEST-1234',
        amount: 100.0,
        currency: 'PHP',
        issuer: new IssuerData(
            id: 1,
        ),
        cost: new PricingEstimateData(
            currency: 'PHP',
            base_fee: 1.0,
            components: [],
            total: 1.0,
        ),
        wallet: [
            'balance_before' => 1000.0,
            'balance_after' => 999.0,
        ],
        debit: new DebitData(
            id: 501,
            amount: null,
        ),
        links: new PayCodeLinksData(
            redeem: 'https://example.test/disburse?code=TEST-1234',
            redeem_path: '/disburse?code=TEST-1234',
        ),
    );

    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldReceive('handle')
        ->once()
        ->with(Mockery::on(function (array $actual) {
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

    $response = $this->postJson('/api/x/v1/vouchers', $payload);

    $response
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'data' => $result->toArray(),
            'meta' => [
                'idempotency' => [
                    'key' => null,
                    'replayed' => false,
                ],
            ],
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.voucher_id', 99)
        ->assertJsonPath('data.code', 'TEST-1234')
        ->assertJsonPath('meta.idempotency.replayed', false);
});

it('validates required payload fields for voucher generation through the lifecycle route surface', function () {
    $response = $this->postJson('/api/x/v1/vouchers', []);

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
