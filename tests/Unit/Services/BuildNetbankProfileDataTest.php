<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Services\BuildNetbankProfileData;
use LBHurtado\XChange\Services\CheckNetbankSourceAccountReadiness;

it('builds NetBank profile data from provider runtime and NetBank config', function () {
    config()->set('disbursement.client.alias', '91500');
    config()->set('disbursement.source.account_number', '113-001-00001-9');
    config()->set('disbursement.source.sender.customer_id', '90627');

    $settings = Mockery::mock(ProviderRuntimeSettingsResolverContract::class);
    $settings->shouldReceive('provider')->once()->andReturn('netbank');

    $sourceAccount = Mockery::mock(CheckNetbankSourceAccountReadiness::class);
    $sourceAccount->shouldReceive('handle')
        ->once()
        ->withNoArgs()
        ->andReturn([
            'enabled' => false,
            'ready' => true,
            'checked' => false,
            'message' => 'NetBank source-account readiness check is disabled.',
        ]);

    $profile = (new BuildNetbankProfileData($settings, $sourceAccount))->handle();

    expect($profile['active'])->toBeTrue()
        ->and($profile['client_alias'])->toBe('91500')
        ->and($profile['source_account_number'])->toBe('113-001-00001-9')
        ->and($profile['sender_customer_id'])->toBe('90627')
        ->and($profile['source_account_readiness']['ready'])->toBeTrue();
});
