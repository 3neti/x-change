<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\ProviderProvisioningManagerContract;
use LBHurtado\XChange\Contracts\XChangeOnboardingGatewayContract;
use LBHurtado\XChange\Services\ResumeProviderProvisioningFromOnboarding;

it('starts provider provisioning when onboarding becomes ready', function () {
    $owner = new class
    {
        public int $id = 1;
    };

    $onboarding = Mockery::mock(XChangeOnboardingGatewayContract::class);
    $onboarding->shouldReceive('ensureReady')
        ->once()
        ->with('onb-123')
        ->andReturn([
            'ready' => true,
            'reference' => 'onb-123',
        ]);

    $provisioning = Mockery::mock(ProviderProvisioningManagerContract::class);
    $provisioning->shouldReceive('startOrResume')
        ->once()
        ->with($owner, [
            'provider' => 'paynamics',
            'mode' => 'wallet_create',
            'purpose' => 'IssuePayCode',
        ])
        ->andReturn([
            'ready' => true,
            'provider' => 'paynamics',
        ]);

    $result = (new ResumeProviderProvisioningFromOnboarding($onboarding, $provisioning))->handle(
        'onb-123',
        $owner,
        [
            'provider' => 'paynamics',
            'mode' => 'wallet_create',
            'purpose' => 'IssuePayCode',
        ],
    );

    expect($result)->toMatchArray([
        'reference' => 'onb-123',
        'ready' => true,
        'onboarding' => [
            'ready' => true,
            'reference' => 'onb-123',
        ],
        'provisioning' => [
            'ready' => true,
            'provider' => 'paynamics',
        ],
    ]);
});
