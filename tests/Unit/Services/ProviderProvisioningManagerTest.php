<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\ProviderProvisioningManagerContract;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;
use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Tests\Fakes\User;

it('routes NetBank provisioning through the configured gateway and persists a ready link', function () {
    $owner = User::query()->create([
        'name' => 'Netbank Gateway Owner',
        'email' => 'netbank-gateway@example.test',
        'mobile' => '639171234568',
        'password' => 'password',
    ]);

    $result = app(ProviderProvisioningManagerContract::class)->startOrResume($owner, [
        'provider' => 'netbank',
        'mode' => ProviderProvisioningMode::BankAccountLink->value,
        'purpose' => 'BankOnboardingRequired',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******1987',
    ]);

    expect($result)->toMatchArray([
        'provider' => 'netbank',
        'mode' => ProviderProvisioningMode::BankAccountLink->value,
        'status' => 'ready',
        'ready' => true,
    ]);

    $link = ProviderAccountLink::query()->findOrFail(data_get($result, 'link_id'));

    expect($link)->toBeInstanceOf(ProviderAccountLink::class)
        ->and($link->provider)->toBe('netbank')
        ->and($link->mode)->toBe(ProviderProvisioningMode::BankAccountLink->value)
        ->and($link->status)->toBe('ready')
        ->and($link->provider_bank_account_id)->toBe('NETBANK-GXCHPHM2XXX-XXXXXXX1987')
        ->and(data_get($link->metadata, 'bank_code'))->toBe('GXCHPHM2XXX');
});
