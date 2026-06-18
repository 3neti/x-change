<?php

declare(strict_types=1);

use LBHurtado\Onboarding\Data\OnboardingRequirementsData;
use LBHurtado\Onboarding\Data\OnboardingResultData;
use LBHurtado\Onboarding\Data\OnboardingStatusData;
use LBHurtado\Onboarding\Data\OnboardingSubjectData;
use LBHurtado\Onboarding\Enums\IdentityLevel;
use LBHurtado\Onboarding\Enums\OnboardingStatus;
use LBHurtado\Onboarding\Models\OnboardingSession;
use LBHurtado\XChange\Contracts\ProviderProvisioningManagerContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\UserResolverContract;
use LBHurtado\XChange\Services\StartProviderProvisioningFromOnboardingCompletion;
use LBHurtado\XChange\Tests\Fakes\User;

it('starts provisioning from completed issuer onboarding sessions', function () {
    if (! class_exists(OnboardingSession::class)) {
        $this->markTestSkipped('3neti/onboarding is not installed in the package test environment.');
    }

    config()->set('x-change.onboarding.issuer_model', User::class);

    $owner = User::query()->create([
        'name' => 'Issuer Owner',
        'email' => 'issuer-owner@example.test',
        'mobile' => '639171234500',
        'password' => 'password',
    ]);

    OnboardingSession::query()->create([
        'reference' => 'onb-issuer-123',
        'subject_type' => 'external',
        'subject_id' => null,
        'mobile' => $owner->mobile,
        'email' => $owner->email,
        'purpose' => 'issue_pay_code',
        'identity_level' => IdentityLevel::Verified,
        'status' => OnboardingStatus::Completed,
        'requirements' => [],
        'payload' => [
            'provider' => 'paynamics',
            'mode' => 'wallet_create',
            'metadata' => [
                'issuer_id' => $owner->getKey(),
            ],
        ],
        'meta' => [],
        'result' => [],
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    $users = Mockery::mock(UserResolverContract::class);
    $users->shouldReceive('resolve')
        ->once()
        ->with(Mockery::subset([
            'issuer_id' => $owner->getKey(),
            'mobile' => $owner->mobile,
            'email' => $owner->email,
        ]))
        ->andReturn($owner);

    $provisioning = Mockery::mock(ProviderProvisioningManagerContract::class);
    $provisioning->shouldReceive('startOrResume')
        ->once()
        ->with($owner, Mockery::subset([
            'provider' => 'paynamics',
            'mode' => 'wallet_create',
            'purpose' => 'IssuePayCode',
            'issuer_id' => $owner->getKey(),
            'mobile' => $owner->mobile,
            'email' => $owner->email,
        ]))
        ->andReturn([
            'provider' => 'paynamics',
            'mode' => 'wallet_create',
            'ready' => true,
        ]);

    $result = new OnboardingResultData(
        reference: 'onb-issuer-123',
        subject: new OnboardingSubjectData(
            mobile: $owner->mobile,
            email: $owner->email,
            name: $owner->name,
        ),
        requirements: new OnboardingRequirementsData(
            identityLevel: IdentityLevel::Verified,
            requiresKyc: true,
            requiresWallet: true,
            requiresBankAccount: false,
            requiresMobileVerification: true,
        ),
        status: new OnboardingStatusData(
            status: OnboardingStatus::Completed,
            reference: 'onb-issuer-123',
        ),
    );

    $service = new StartProviderProvisioningFromOnboardingCompletion(
        provisioning: $provisioning,
        settings: app(ProviderRuntimeSettingsResolverContract::class),
        users: $users,
    );

    expect($service->handle($result))->toMatchArray([
        'provider' => 'paynamics',
        'mode' => 'wallet_create',
        'ready' => true,
    ]);
});
