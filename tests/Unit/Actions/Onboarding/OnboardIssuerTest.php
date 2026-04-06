<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Onboarding\OnboardIssuer;
use LBHurtado\XChange\Contracts\IssuerOnboardingContract;

it('onboards issuer through the onboarding contract and returns normalized issuer payload', function () {
    $input = [
        'name' => 'Issuer Name',
        'email' => 'issuer@example.com',
        'mobile' => '09171234567',
        'country' => 'PH',
        'metadata' => [],
    ];

    $issuer = (object) [
        'id' => 1,
        'name' => 'Issuer Name',
        'email' => 'issuer@example.com',
        'mobile' => '09171234567',
        'country' => 'PH',
    ];

    $service = Mockery::mock(IssuerOnboardingContract::class);
    $service->shouldReceive('onboard')
        ->once()
        ->with($input)
        ->andReturn($issuer);

    $action = new OnboardIssuer($service);

    $result = $action->handle($input);

    expect($result)->toBe([
        'issuer' => [
            'id' => 1,
            'name' => 'Issuer Name',
            'email' => 'issuer@example.com',
            'mobile' => '09171234567',
            'country' => 'PH',
        ],
    ]);
});
