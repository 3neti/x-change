<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\XChangeOnboardingGatewayContract;

it('returns an unavailable issuer onboarding result when onboarding is not registered', function () {
    $result = app(XChangeOnboardingGatewayContract::class)->startIssuer([
        'mobile' => '09171234567',
        'name' => 'Issuer',
    ]);

    expect($result)->toMatchArray([
        'available' => false,
        'status' => 'unavailable',
        'purpose' => 'IssuePayCode',
        'mobile' => '09171234567',
    ]);
});

it('returns an unavailable redemption onboarding result when onboarding is not registered', function () {
    $result = app(XChangeOnboardingGatewayContract::class)->startRedemption([
        'mobile' => '09173011987',
        'disbursement' => [
            'bank_onboarding' => 'required',
        ],
    ]);

    expect($result)->toMatchArray([
        'available' => false,
        'status' => 'unavailable',
        'purpose' => 'BankOnboardingRequired',
        'mobile' => '09173011987',
    ]);
});

it('requires an onboarding reference before readiness can be checked', function () {
    app(XChangeOnboardingGatewayContract::class)->ensureReady(null);
})->throws(InvalidArgumentException::class, 'An onboarding reference is required.');
