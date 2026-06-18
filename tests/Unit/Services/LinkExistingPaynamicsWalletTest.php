<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use LBHurtado\EmiCore\Enums\ComplianceLevel;
use LBHurtado\EmiCore\Enums\ProviderCode;
use LBHurtado\EmiCore\Enums\VerificationStatus;
use LBHurtado\EmiCore\Enums\WalletStatus;
use LBHurtado\EmiCore\Enums\WalletType;
use LBHurtado\EmiCore\Models\Wallet as EmiWallet;
use LBHurtado\XChange\Contracts\ProviderAccountLinkRepositoryContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;
use LBHurtado\XChange\Services\LinkExistingPaynamicsWallet;
use LBHurtado\XChange\Services\SyncPaynamicsWalletBalance;
use LBHurtado\XChange\Tests\Fakes\User;

it('links an existing Paynamics wallet after syncing provider balance', function () {
    $owner = User::query()->create([
        'name' => 'Existing Wallet Owner',
        'email' => 'existing-wallet-owner@example.test',
        'mobile' => '639171234564',
        'password' => 'password',
    ]);

    $wallet = EmiWallet::unguarded(function () use ($owner): EmiWallet {
        return EmiWallet::query()->create([
            'holder_type' => $owner::class,
            'holder_id' => $owner->getKey(),
            'name' => 'Paynamics Customer Wallet',
            'slug' => 'paynamics-cnstwlltlinked01',
            'uuid' => (string) Str::uuid(),
            'description' => 'Provider wallet projection',
            'balance' => 0,
            'decimal_places' => 2,
            'provider_code' => ProviderCode::PaynamicsConstellation->value,
            'provider_wallet_id' => 'CNSTWLLTLINKED01',
            'wallet_type' => WalletType::Customer->value,
            'status' => WalletStatus::Active->value,
            'compliance_level' => ComplianceLevel::Level1->value,
            'verification_status' => VerificationStatus::Approved->value,
            'balance_cached' => '80.30',
            'currency' => 'PHP',
        ]);
    });

    $settings = Mockery::mock(ProviderRuntimeSettingsResolverContract::class);
    $settings->shouldReceive('topology')->once()->with('paynamics')->andReturn('provider_customer_wallet');

    $balances = Mockery::mock(SyncPaynamicsWalletBalance::class);
    $balances->shouldReceive('handle')
        ->once()
        ->with('CNSTWLLTLINKED01', $owner)
        ->andReturn([
            'wallet' => $wallet,
            'response' => ['wallet_id' => 'CNSTWLLTLINKED01'],
            'balance_minor' => 8030,
            'balance' => 80.30,
            'currency' => 'PHP',
        ]);

    $link = (new LinkExistingPaynamicsWallet(
        $balances,
        app(ProviderAccountLinkRepositoryContract::class),
        $settings,
    ))->handle($owner, 'cnstwlltlinked01');

    expect($link->provider)->toBe('paynamics')
        ->and($link->topology)->toBe('provider_customer_wallet')
        ->and($link->mode)->toBe(ProviderProvisioningMode::WalletResolve->value)
        ->and($link->status)->toBe('ready')
        ->and($link->provider_wallet_id)->toBe('CNSTWLLTLINKED01')
        ->and($link->emi_wallet_id)->toBe($wallet->getKey())
        ->and($link->verification_status)->toBe('unverified_manual_link')
        ->and($link->identity_level)->toBe('wallet_exists_only')
        ->and($link->metadata['ownership_verification_required'])->toBeTrue();
});
