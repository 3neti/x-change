<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Onboarding\OnboardIssuer;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\Onboarding\OnboardIssuerResultData;

it('returns an onboarded issuer via api', function () {
    $payload = validOnboardIssuerPayload();

    $result = new OnboardIssuerResultData(
        issuer: new IssuerData(
            id: 1,
            name: 'Issuer Name',
            email: 'issuer@example.com',
            mobile: '09171234567',
            country: 'PH',
        ),
    );

    $action = Mockery::mock(OnboardIssuer::class);
    $action->shouldReceive('handle')
        ->once()
        ->with($payload)
        ->andReturn($result);

    $this->app->instance(OnboardIssuer::class, $action);

    $response = $this->postJson(xchangeApi('onboarding/issuers'), $payload);

    $response
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'data' => $result->toArray(),
            'meta' => [],
        ]);
});

it('validates required payload fields for issuer onboarding', function () {
    $response = $this->postJson(xchangeApi('onboarding/issuers'), []);

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
                'name',
                'mobile',
            ],
        ]);
});
