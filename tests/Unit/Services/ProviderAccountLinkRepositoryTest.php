<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\ProviderAccountLinkRepositoryContract;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;
use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Tests\Fakes\User;

it('stores and resolves a ready provider account link for an owner', function () {
    $owner = User::query()->create([
        'name' => 'Provider Owner',
        'email' => 'provider-owner@example.test',
        'mobile' => '639171234567',
        'password' => 'password',
    ]);

    $repository = app(ProviderAccountLinkRepositoryContract::class);

    $link = $repository->storeFromProvisioningResult($owner, [
        'provider' => 'paynamics',
        'topology' => 'provider_customer_wallet',
        'mode' => ProviderProvisioningMode::WalletCreate->value,
        'status' => 'ready',
        'provider_wallet_id' => 'CNSTWLLTFAKE01',
        'metadata' => [
            'merchant_key' => 'secret',
            'safe' => 'value',
        ],
    ]);

    $ready = $repository->findReadyForOwner($owner, 'paynamics', ProviderProvisioningMode::WalletCreate->value);

    expect($link)->toBeInstanceOf(ProviderAccountLink::class)
        ->and($link->isReady())->toBeTrue()
        ->and($ready?->provider_wallet_id)->toBe('CNSTWLLTFAKE01')
        ->and($ready?->metadata)->toMatchArray([
            'merchant_key' => '[redacted]',
            'safe' => 'value',
        ]);
});

it('does not resolve pending provider account links as ready', function () {
    $owner = User::query()->create([
        'name' => 'Pending Owner',
        'email' => 'pending-owner@example.test',
        'mobile' => '639171234568',
        'password' => 'password',
    ]);

    $repository = app(ProviderAccountLinkRepositoryContract::class);

    $repository->storeFromProvisioningResult($owner, [
        'provider' => 'netbank',
        'topology' => 'ledger_pooled',
        'mode' => ProviderProvisioningMode::BankAccountLink->value,
        'status' => 'pending',
    ]);

    expect($repository->findReadyForOwner($owner, 'netbank', ProviderProvisioningMode::BankAccountLink->value))->toBeNull()
        ->and($repository->findLatestForOwner($owner, 'netbank', ProviderProvisioningMode::BankAccountLink->value)?->status)->toBe('pending');
});
