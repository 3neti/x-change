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
use LBHurtado\XChange\Contracts\ProviderProvisioningGatewayContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use LBHurtado\XChange\Services\SyncPaynamicsWalletBalance;
use LBHurtado\XChange\Tests\Fakes\User;

it('builds a local ledger balance overview for NetBank topology', function () {
    $owner = new stdClass;
    $wallet = (object) ['balance' => 250000];

    $settings = Mockery::mock(ProviderRuntimeSettingsResolverContract::class);
    $settings->shouldReceive('provider')->once()->with(null)->andReturn('netbank');
    $settings->shouldReceive('topology')->once()->with('netbank')->andReturn('ledger_pooled');

    $links = Mockery::mock(ProviderAccountLinkRepositoryContract::class);
    $provisioning = Mockery::mock(ProviderProvisioningGatewayContract::class);

    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets->shouldReceive('resolveForUser')->once()->with($owner)->andReturn($wallet);
    $wallets->shouldReceive('getBalance')->once()->with($wallet)->andReturn(250000);

    $overview = (new BuildBalanceOverview($settings, $links, $provisioning, $wallets))->handle($owner);

    expect($overview['provider'])->toBe('netbank')
        ->and($overview['topology'])->toBe('ledger_pooled')
        ->and($overview['authority'])->toBe('local_ledger')
        ->and((float) $overview['authoritative']['balance'])->toBe(2500.0)
        ->and($overview['authoritative']['is_authoritative'])->toBeTrue()
        ->and($overview['sync_status'])->toBe('not_required');
});

it('refreshes a stale Paynamics provider wallet projection before building the overview', function () {
    config()->set('x-change.funding.provider_balance_max_age_seconds', 60);

    $owner = User::query()->create([
        'name' => 'Balance Owner',
        'email' => 'balance-owner@example.test',
        'mobile' => '639171234563',
        'password' => 'password',
    ]);

    $link = app(ProviderAccountLinkRepositoryContract::class)->storeFromProvisioningResult($owner, [
        'provider' => 'paynamics',
        'topology' => 'provider_customer_wallet',
        'mode' => ProviderProvisioningMode::WalletCreate->value,
        'status' => 'ready',
        'provider_wallet_id' => 'CNSTWLLTBALANCE01',
    ]);

    EmiWallet::unguarded(function () use ($owner): void {
        EmiWallet::query()->create([
            'holder_type' => $owner::class,
            'holder_id' => $owner->getKey(),
            'name' => 'Paynamics Customer Wallet',
            'slug' => 'paynamics-cnstwlltbalance01',
            'uuid' => (string) Str::uuid(),
            'description' => 'Provider wallet projection',
            'balance' => 0,
            'decimal_places' => 2,
            'provider_code' => ProviderCode::PaynamicsConstellation->value,
            'provider_wallet_id' => 'CNSTWLLTBALANCE01',
            'wallet_type' => WalletType::Customer->value,
            'status' => WalletStatus::Active->value,
            'compliance_level' => ComplianceLevel::Level1->value,
            'verification_status' => VerificationStatus::Approved->value,
            'balance_cached' => '100.00',
            'currency' => 'PHP',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);
    });

    $settings = Mockery::mock(ProviderRuntimeSettingsResolverContract::class);
    $settings->shouldReceive('provider')->once()->with(null)->andReturn('paynamics');
    $settings->shouldReceive('topology')->once()->with('paynamics')->andReturn('provider_customer_wallet');

    $provisioning = Mockery::mock(ProviderProvisioningGatewayContract::class);
    $provisioning->shouldNotReceive('refresh');

    $paynamicsBalances = Mockery::mock(SyncPaynamicsWalletBalance::class);
    $paynamicsBalances->shouldReceive('handle')
        ->once()
        ->with('CNSTWLLTBALANCE01', $owner)
        ->andReturnUsing(function () {
            EmiWallet::query()
                ->where('provider_wallet_id', 'CNSTWLLTBALANCE01')
                ->update([
                    'balance_cached' => '1500.00',
                    'updated_at' => now(),
                ]);

            return [
                'wallet' => EmiWallet::query()->where('provider_wallet_id', 'CNSTWLLTBALANCE01')->firstOrFail(),
                'response' => [],
                'balance_minor' => 150000,
                'balance' => 1500.0,
                'currency' => 'PHP',
            ];
        });

    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets->shouldReceive('resolveForUser')->once()->with($owner)->andThrow(new RuntimeException('No local wallet.'));

    $overview = (new BuildBalanceOverview(
        $settings,
        app(ProviderAccountLinkRepositoryContract::class),
        $provisioning,
        $wallets,
        $paynamicsBalances,
    ))->handle($owner);

    expect($overview['provider'])->toBe('paynamics')
        ->and($overview['authority'])->toBe('provider_wallet')
        ->and($overview['sync_status'])->toBe('synced')
        ->and((float) $overview['authoritative']['balance'])->toBe(1500.0)
        ->and($overview['authoritative']['is_stale'])->toBeFalse();
});

it('uses a linked Paynamics wallet when the runtime default provider is still manual', function () {
    config()->set('x-change.funding.provider_balance_max_age_seconds', 60);

    $owner = User::query()->create([
        'name' => 'Linked Manual Default Owner',
        'email' => 'linked-manual-default@example.test',
        'mobile' => '639171234567',
        'password' => 'password',
    ]);

    app(ProviderAccountLinkRepositoryContract::class)->storeFromProvisioningResult($owner, [
        'provider' => 'paynamics',
        'topology' => 'provider_customer_wallet',
        'mode' => ProviderProvisioningMode::WalletResolve->value,
        'status' => 'ready',
        'provider_wallet_id' => 'CNSTWLLTLINKEDMANUAL01',
    ]);

    EmiWallet::unguarded(function () use ($owner): void {
        EmiWallet::query()->create([
            'holder_type' => $owner::class,
            'holder_id' => $owner->getKey(),
            'name' => 'Linked Paynamics Customer Wallet',
            'slug' => 'paynamics-cnstwlltlinkedmanual01',
            'uuid' => (string) Str::uuid(),
            'description' => 'Provider wallet projection',
            'balance' => 0,
            'decimal_places' => 2,
            'provider_code' => ProviderCode::PaynamicsConstellation->value,
            'provider_wallet_id' => 'CNSTWLLTLINKEDMANUAL01',
            'wallet_type' => WalletType::Customer->value,
            'status' => WalletStatus::Active->value,
            'compliance_level' => ComplianceLevel::Level1->value,
            'verification_status' => VerificationStatus::Approved->value,
            'balance_cached' => '80.30',
            'currency' => 'PHP',
        ]);
    });

    $settings = Mockery::mock(ProviderRuntimeSettingsResolverContract::class);
    $settings->shouldReceive('provider')->once()->with(null)->andReturn('manual');
    $settings->shouldReceive('topology')->once()->with('paynamics')->andReturn('provider_customer_wallet');

    $provisioning = Mockery::mock(ProviderProvisioningGatewayContract::class);
    $provisioning->shouldNotReceive('refresh');

    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets->shouldReceive('resolveForUser')->once()->with($owner)->andThrow(new RuntimeException('No local wallet.'));

    $overview = (new BuildBalanceOverview(
        $settings,
        app(ProviderAccountLinkRepositoryContract::class),
        $provisioning,
        $wallets,
    ))->handle($owner);

    expect($overview['provider'])->toBe('paynamics')
        ->and($overview['authority'])->toBe('provider_wallet')
        ->and((float) $overview['authoritative']['balance'])->toBe(80.30)
        ->and($overview['authoritative']['provider_wallet_id'])->toBe('CNSTWLLTLINKEDMANUAL01');
});
