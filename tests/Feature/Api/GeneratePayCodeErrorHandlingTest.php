<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Exceptions\PayCodeIssuerNotResolved;
use LBHurtado\XChange\Exceptions\PayCodeWalletNotResolved;

it('returns 401 when issuer cannot be resolved', function () {
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

    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldReceive('handle')
        ->once()
        ->andThrow(new PayCodeIssuerNotResolved('Unable to resolve Pay Code issuer.'));

    $this->app->instance(GeneratePayCode::class, $action);

    $response = $this->postJson(xchangeApi('pay-codes'), $payload);

    $response
        ->assertUnauthorized()
        ->assertJson([
            'success' => false,
            'code' => 'PAY_CODE_ISSUER_NOT_RESOLVED',
            'message' => 'Unable to resolve Pay Code issuer.',
        ]);
});

it('returns 422 when wallet cannot be resolved', function () {
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

    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldReceive('handle')
        ->once()
        ->andThrow(new PayCodeWalletNotResolved('Issuer wallet was not found.'));

    $this->app->instance(GeneratePayCode::class, $action);

    $response = $this->postJson(xchangeApi('pay-codes'), $payload);

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'code' => 'PAY_CODE_WALLET_NOT_RESOLVED',
            'message' => 'Issuer wallet was not found.',
        ]);
});

it('returns 500 when pay code issuance fails', function () {
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

    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldReceive('handle')
        ->once()
        ->andThrow(new PayCodeIssuanceFailed('Pay Code issuance did not return a voucher.'));

    $this->app->instance(GeneratePayCode::class, $action);

    $response = $this->postJson(xchangeApi('pay-codes'), $payload);

    $response
        ->assertStatus(500)
        ->assertJson([
            'success' => false,
            'code' => 'PAY_CODE_ISSUANCE_FAILED',
            'message' => 'Pay Code issuance did not return a voucher.',
        ]);
});
