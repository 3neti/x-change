<?php

declare(strict_types=1);

use LBHurtado\Onboarding\Contracts\OnboardingServiceContract;
use LBHurtado\Onboarding\Data\OnboardingRequirementsData;
use LBHurtado\Onboarding\Data\OnboardingResultData;
use LBHurtado\Onboarding\Data\OnboardingStatusData;
use LBHurtado\Onboarding\Data\OnboardingSubjectData;
use LBHurtado\Onboarding\Enums\IdentityLevel;
use LBHurtado\Onboarding\Enums\OnboardingStatus;
use LBHurtado\XChange\Services\ProvisioningAwareOnboardingService;
use LBHurtado\XChange\Services\StartProviderProvisioningFromOnboardingCompletion;

it('merges provisioning state into onboarding completion metadata', function () {
    if (! class_exists(OnboardingResultData::class)) {
        $this->markTestSkipped('3neti/onboarding is not installed in the package test environment.');
    }

    $result = new OnboardingResultData(
        reference: 'onb-123',
        subject: new OnboardingSubjectData(mobile: '639171234501'),
        requirements: new OnboardingRequirementsData(
            identityLevel: IdentityLevel::Verified,
            requiresKyc: true,
            requiresWallet: true,
            requiresBankAccount: false,
            requiresMobileVerification: true,
        ),
        status: new OnboardingStatusData(
            status: OnboardingStatus::Completed,
            reference: 'onb-123',
        ),
        meta: [
            'flow' => [
                'url' => '/onboarding/onb-123',
            ],
        ],
    );

    $onboarding = Mockery::mock(OnboardingServiceContract::class);
    $onboarding->shouldReceive('complete')
        ->once()
        ->with('onb-123', ['otp' => '123456'])
        ->andReturn($result);

    $hook = Mockery::mock(StartProviderProvisioningFromOnboardingCompletion::class);
    $hook->shouldReceive('handle')
        ->once()
        ->with($result)
        ->andReturn([
            'provider' => 'paynamics',
            'mode' => 'wallet_create',
            'ready' => false,
        ]);

    $service = new ProvisioningAwareOnboardingService($onboarding, $hook);

    $completed = $service->complete('onb-123', ['otp' => '123456']);

    expect(data_get($completed->meta, 'xchange.provisioning'))->toMatchArray([
        'provider' => 'paynamics',
        'mode' => 'wallet_create',
        'ready' => false,
    ]);
});
