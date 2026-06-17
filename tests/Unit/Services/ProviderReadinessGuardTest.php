<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\ProviderProvisioningManagerContract;
use LBHurtado\XChange\Contracts\ProviderReadinessGuardContract;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;
use LBHurtado\XChange\Tests\Fakes\User;

function providerReadinessUser(string $email): User
{
    return User::query()->create([
        'name' => 'Provider Readiness',
        'email' => $email,
        'mobile' => '63917'.fake()->numerify('#######'),
        'password' => 'password',
    ]);
}

it('blocks Paynamics issuer readiness when provider wallet link is missing', function () {
    $owner = providerReadinessUser('missing-wallet@example.test');

    $readiness = app(ProviderReadinessGuardContract::class)
        ->evaluateIssuer($owner, 'paynamics');

    expect($readiness->ready)->toBeFalse()
        ->and($readiness->status)->toBe('blocked')
        ->and($readiness->provider)->toBe('paynamics')
        ->and($readiness->topology)->toBe('provider_customer_wallet')
        ->and($readiness->missing)->toContain('provider_customer_wallet');
});

it('allows Paynamics issuer readiness when provider wallet link is ready', function () {
    $owner = providerReadinessUser('ready-wallet@example.test');

    app(ProviderProvisioningManagerContract::class)->startOrResume($owner, [
        'provider' => 'paynamics',
        'mode' => ProviderProvisioningMode::WalletCreate->value,
        'status' => 'ready',
    ]);

    $readiness = app(ProviderReadinessGuardContract::class)
        ->evaluateIssuer($owner, 'paynamics');

    expect($readiness->ready)->toBeTrue()
        ->and($readiness->status)->toBe('ready')
        ->and($readiness->linkId)->not->toBeNull();
});

it('blocks claim readiness when bank account is required but missing', function () {
    $owner = providerReadinessUser('missing-bank@example.test');

    $readiness = app(ProviderReadinessGuardContract::class)
        ->evaluateClaimant($owner, 'netbank', ['requires_bank_account' => true]);

    expect($readiness->ready)->toBeFalse()
        ->and($readiness->status)->toBe('blocked')
        ->and($readiness->missing)->toContain('bank_account_link');
});

it('allows claim readiness when required bank account link is ready', function () {
    $owner = providerReadinessUser('ready-bank@example.test');

    app(ProviderProvisioningManagerContract::class)->startOrResume($owner, [
        'provider' => 'netbank',
        'mode' => ProviderProvisioningMode::BankAccountLink->value,
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******1987',
    ]);

    $readiness = app(ProviderReadinessGuardContract::class)
        ->evaluateClaimant($owner, 'netbank', ['requires_bank_account' => true]);

    expect($readiness->ready)->toBeTrue()
        ->and($readiness->status)->toBe('ready')
        ->and($readiness->mode)->toBe(ProviderProvisioningMode::BankAccountLink->value);
});
