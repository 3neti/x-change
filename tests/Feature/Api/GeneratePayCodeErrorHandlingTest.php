<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Exceptions\PayCodeWalletNotResolved;
use LBHurtado\XChange\Exceptions\ProviderProvisioningRequired;

it('returns 401 when request is unauthenticated', function (): void {
    $payload = validPayCodePayload();

    $response = $this->postJson(xchangeApi('pay-codes'), $payload);

    $response
        ->assertUnauthorized()
        ->assertJson([
            'success' => false,
            'code' => 'UNAUTHENTICATED',
            'message' => 'Unauthenticated.',
        ]);
});

it('returns 422 when wallet cannot be resolved', function (): void {
    actingAsTestUser();

    $payload = validPayCodePayload();

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

it('returns 500 when pay code issuance fails', function (): void {
    actingAsTestUser();

    $payload = validPayCodePayload();

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

it('returns 409 when provider provisioning is required before issuance', function (): void {
    actingAsTestUser();

    $payload = validPayCodePayload();

    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldReceive('handle')
        ->once()
        ->andThrow(new ProviderProvisioningRequired(
            'Pay Code issuance requires provider provisioning before the voucher can be created.',
            [
                'provider' => 'paynamics',
                'mode' => 'wallet_create',
                'descriptor' => [
                    'title' => 'Create your Paynamics wallet',
                    'steps' => ['profile', 'wallet', 'kyc', 'ready'],
                ],
            ],
        ));

    $this->app->instance(GeneratePayCode::class, $action);

    $response = $this->postJson(xchangeApi('pay-codes'), $payload);

    $response
        ->assertStatus(409)
        ->assertJson([
            'success' => false,
            'code' => 'PROVIDER_PROVISIONING_REQUIRED',
            'message' => 'Pay Code issuance requires provider provisioning before the voucher can be created.',
            'errors' => [
                'provisioning' => [
                    'provider' => 'paynamics',
                    'mode' => 'wallet_create',
                    'descriptor' => [
                        'title' => 'Create your Paynamics wallet',
                        'steps' => ['profile', 'wallet', 'kyc', 'ready'],
                    ],
                ],
            ],
        ]);
});
