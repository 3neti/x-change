<?php

declare(strict_types=1);

use LBHurtado\XChange\Tests\Fakes\User;

it('onboards an issuer end to end via api', function () {
    config()->set('x-change.onboarding.issuer_model', User::class);

    $payload = validOnboardIssuerPayload();

    $response = $this->postJson(xchangeApi('onboarding/issuers'), $payload);

    $response
        ->assertCreated()
        ->assertJsonStructure([
            'success',
            'data' => [
                'issuer' => [
                    'id',
                    'name',
                    'email',
                    'mobile',
                    'country',
                ],
            ],
            'meta',
        ]);

    $issuerId = $response->json('data.issuer.id');

    expect($issuerId)->not->toBeNull();

    $issuer = User::query()->find($issuerId);

    expect($issuer)->not->toBeNull();
    expect($issuer?->name)->toBe('Issuer Name');
    expect($issuer?->email)->toBe('issuer@example.com');
});
