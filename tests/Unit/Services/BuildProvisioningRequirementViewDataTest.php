<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\BuildProvisioningRequirementViewData;

it('backfills descriptor data for frontend provisioning requirements', function () {
    $result = app(BuildProvisioningRequirementViewData::class)->handle([
        'provider' => 'netbank',
        'mode' => 'bank_account_link',
        'reason' => 'Bank account readiness is missing.',
        'readiness' => [
            'topology' => 'ledger_pooled',
        ],
        'onboarding' => [
            'reference' => 'onb-claim-123',
        ],
    ]);

    expect(data_get($result, 'provider'))->toBe('netbank');
    expect(data_get($result, 'mode'))->toBe('bank_account_link');
    expect(data_get($result, 'topology'))->toBe('ledger_pooled');
    expect(data_get($result, 'reason'))->toBe('Bank account readiness is missing.');
    expect(data_get($result, 'onboarding.reference'))->toBe('onb-claim-123');
    expect(data_get($result, 'descriptor.title'))->toBe('Add payout destination');

    $statusUrl = data_get($result, 'onboarding.links.status_url');
    $resumeUrl = data_get($result, 'onboarding.links.resume_url');

    expect(is_null($statusUrl) || is_string($statusUrl))->toBeTrue();
    expect(is_null($resumeUrl) || is_string($resumeUrl))->toBeTrue();
});
