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
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Services\ProviderAwareFundingPolicy;
use LBHurtado\XChange\Services\SyncPaynamicsWalletBalance;
use LBHurtado\XChange\Tests\Fakes\User;

it('uses the local ledger balance for ledger pooled providers', function () {
    $owner = new stdClass;
    $wallet = (object) ['balance' => 5000];

    $settings = Mockery::mock(ProviderRuntimeSettingsResolverContract::class);
    $settings->shouldReceive('provider')->once()->with('netbank')->andReturn('netbank');
    $settings->shouldReceive('topology')->once()->with('netbank')->andReturn('ledger_pooled');

    $links = Mockery::mock(ProviderAccountLinkRepositoryContract::class);
    $provisioning = Mockery::mock(ProviderProvisioningGatewayContract::class);

    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets->shouldReceive('getBalance')->once()->with($wallet)->andReturn(5000);

    $decision = (new ProviderAwareFundingPolicy($settings, $links, $provisioning, $wallets))
        ->assertCanIssue($owner, $wallet, 25.00, ['provider' => 'netbank']);

    expect($decision->allowed)->toBeTrue()
        ->and($decision->authority)->toBe('local_ledger')
        ->and($decision->available_minor)->toBe(5000)
        ->and($decision->required_minor)->toBe(2500);
});

it('blocks ledger pooled providers when the local ledger balance is insufficient', function () {
    $owner = new stdClass;
    $wallet = (object) ['balance' => 1000];

    $settings = Mockery::mock(ProviderRuntimeSettingsResolverContract::class);
    $settings->shouldReceive('provider')->once()->with('netbank')->andReturn('netbank');
    $settings->shouldReceive('topology')->once()->with('netbank')->andReturn('ledger_pooled');

    $links = Mockery::mock(ProviderAccountLinkRepositoryContract::class);
    $provisioning = Mockery::mock(ProviderProvisioningGatewayContract::class);

    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets->shouldReceive('getBalance')->once()->with($wallet)->andReturn(1000);

    expect(fn () => (new ProviderAwareFundingPolicy($settings, $links, $provisioning, $wallets))
        ->assertCanIssue($owner, $wallet, 25.00, ['provider' => 'netbank']))
        ->toThrow(InsufficientWalletBalance::class, 'Issuer wallet cannot afford the requested amount.');
});

it('uses Paynamics provider wallet balance even when the local Bavix wallet has zero balance', function () {
    $owner = User::query()->create([
        'name' => 'Paynamics Owner',
        'email' => 'paynamics-owner@example.test',
        'mobile' => '639171234560',
        'password' => 'password',
    ]);

    $link = app(ProviderAccountLinkRepositoryContract::class)->storeFromProvisioningResult($owner, [
        'provider' => 'paynamics',
        'topology' => 'provider_customer_wallet',
        'mode' => ProviderProvisioningMode::WalletCreate->value,
        'status' => 'ready',
        'provider_wallet_id' => 'CNSTWLLTPROVIDER01',
    ]);

    EmiWallet::unguarded(function () use ($owner): void {
        EmiWallet::query()->create([
            'holder_type' => $owner::class,
            'holder_id' => $owner->getKey(),
            'name' => 'Paynamics Customer Wallet',
            'slug' => 'paynamics-cnstwlltprovider01',
            'uuid' => (string) Str::uuid(),
            'description' => 'Provider wallet projection',
            'balance' => 0,
            'decimal_places' => 2,
            'provider_code' => ProviderCode::PaynamicsConstellation->value,
            'provider_wallet_id' => 'CNSTWLLTPROVIDER01',
            'wallet_type' => WalletType::Customer->value,
            'status' => WalletStatus::Active->value,
            'compliance_level' => ComplianceLevel::Level1->value,
            'verification_status' => VerificationStatus::Approved->value,
            'balance_cached' => '2000.00',
            'currency' => 'PHP',
        ]);
    });

    $settings = Mockery::mock(ProviderRuntimeSettingsResolverContract::class);
    $settings->shouldReceive('provider')->once()->with('paynamics')->andReturn('paynamics');
    $settings->shouldReceive('topology')->once()->with('paynamics')->andReturn('provider_customer_wallet');

    $provisioning = Mockery::mock(ProviderProvisioningGatewayContract::class);
    $provisioning->shouldNotReceive('refresh');

    $paynamicsBalances = Mockery::mock(SyncPaynamicsWalletBalance::class);
    $paynamicsBalances->shouldReceive('handle')
        ->once()
        ->with('CNSTWLLTPROVIDER01', $owner)
        ->andReturnUsing(function () {
            return [
                'wallet' => EmiWallet::query()->where('provider_wallet_id', 'CNSTWLLTPROVIDER01')->firstOrFail(),
                'response' => [],
                'balance_minor' => 200000,
                'balance' => 2000.0,
                'currency' => 'PHP',
            ];
        });

    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets->shouldNotReceive('getBalance');

    $decision = (new ProviderAwareFundingPolicy(
        $settings,
        app(ProviderAccountLinkRepositoryContract::class),
        $provisioning,
        $wallets,
        $paynamicsBalances,
    ))->assertCanIssue($owner, (object) ['balance' => 0], 1500.00, ['provider' => 'paynamics']);

    expect($decision->allowed)->toBeTrue()
        ->and($decision->authority)->toBe('provider_wallet')
        ->and($decision->available_minor)->toBe(200000)
        ->and($decision->required_minor)->toBe(150000);
});

it('blocks Paynamics issuance when provider wallet refresh fails', function () {
    $owner = User::query()->create([
        'name' => 'Stale Paynamics Owner',
        'email' => 'stale-paynamics-owner@example.test',
        'mobile' => '639171234561',
        'password' => 'password',
    ]);

    app(ProviderAccountLinkRepositoryContract::class)->storeFromProvisioningResult($owner, [
        'provider' => 'paynamics',
        'topology' => 'provider_customer_wallet',
        'mode' => ProviderProvisioningMode::WalletCreate->value,
        'status' => 'ready',
        'provider_wallet_id' => 'CNSTWLLTSTALE01',
    ]);

    $settings = Mockery::mock(ProviderRuntimeSettingsResolverContract::class);
    $settings->shouldReceive('provider')->once()->with('paynamics')->andReturn('paynamics');
    $settings->shouldReceive('topology')->once()->with('paynamics')->andReturn('provider_customer_wallet');

    $provisioning = Mockery::mock(ProviderProvisioningGatewayContract::class);
    $provisioning->shouldNotReceive('refresh');

    $paynamicsBalances = Mockery::mock(SyncPaynamicsWalletBalance::class);
    $paynamicsBalances->shouldReceive('handle')
        ->once()
        ->with('CNSTWLLTSTALE01', $owner)
        ->andThrow(new RuntimeException('Provider unavailable.'));

    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets->shouldNotReceive('getBalance');

    expect(fn () => (new ProviderAwareFundingPolicy(
        $settings,
        app(ProviderAccountLinkRepositoryContract::class),
        $provisioning,
        $wallets,
        $paynamicsBalances,
    ))->assertCanIssue($owner, (object) ['balance' => 0], 1.00, ['provider' => 'paynamics']))
        ->toThrow(InsufficientWalletBalance::class, 'Provider wallet balance could not be refreshed.');
});

it('blocks Paynamics issuance when the provider wallet balance projection is stale', function () {
    config()->set('x-change.funding.provider_balance_max_age_seconds', 60);

    $owner = User::query()->create([
        'name' => 'Stale Projection Owner',
        'email' => 'stale-projection-owner@example.test',
        'mobile' => '639171234562',
        'password' => 'password',
    ]);

    $link = app(ProviderAccountLinkRepositoryContract::class)->storeFromProvisioningResult($owner, [
        'provider' => 'paynamics',
        'topology' => 'provider_customer_wallet',
        'mode' => ProviderProvisioningMode::WalletCreate->value,
        'status' => 'ready',
        'provider_wallet_id' => 'CNSTWLLTSTALE02',
    ]);

    EmiWallet::unguarded(function () use ($owner): void {
        EmiWallet::query()->create([
            'holder_type' => $owner::class,
            'holder_id' => $owner->getKey(),
            'name' => 'Stale Paynamics Customer Wallet',
            'slug' => 'paynamics-cnstwlltstale02',
            'uuid' => (string) Str::uuid(),
            'description' => 'Provider wallet projection',
            'balance' => 0,
            'decimal_places' => 2,
            'provider_code' => ProviderCode::PaynamicsConstellation->value,
            'provider_wallet_id' => 'CNSTWLLTSTALE02',
            'wallet_type' => WalletType::Customer->value,
            'status' => WalletStatus::Active->value,
            'compliance_level' => ComplianceLevel::Level1->value,
            'verification_status' => VerificationStatus::Approved->value,
            'balance_cached' => '2000.00',
            'currency' => 'PHP',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);
    });

    $settings = Mockery::mock(ProviderRuntimeSettingsResolverContract::class);
    $settings->shouldReceive('provider')->once()->with('paynamics')->andReturn('paynamics');
    $settings->shouldReceive('topology')->once()->with('paynamics')->andReturn('provider_customer_wallet');

    $provisioning = Mockery::mock(ProviderProvisioningGatewayContract::class);
    $provisioning->shouldNotReceive('refresh');

    $paynamicsBalances = Mockery::mock(SyncPaynamicsWalletBalance::class);
    $paynamicsBalances->shouldReceive('handle')
        ->once()
        ->with('CNSTWLLTSTALE02', $owner)
        ->andReturnUsing(function () {
            return [
                'wallet' => EmiWallet::query()->where('provider_wallet_id', 'CNSTWLLTSTALE02')->firstOrFail(),
                'response' => [],
                'balance_minor' => 200000,
                'balance' => 2000.0,
                'currency' => 'PHP',
            ];
        });

    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets->shouldNotReceive('getBalance');

    expect(fn () => (new ProviderAwareFundingPolicy(
        $settings,
        app(ProviderAccountLinkRepositoryContract::class),
        $provisioning,
        $wallets,
        $paynamicsBalances,
    ))->assertCanIssue($owner, (object) ['balance' => 0], 1.00, ['provider' => 'paynamics']))
        ->toThrow(InsufficientWalletBalance::class, 'Provider wallet balance snapshot is stale.');
});

it('uses a linked Paynamics wallet for issuance when the runtime default provider is still manual', function () {
    $owner = User::query()->create([
        'name' => 'Manual Default Funding Owner',
        'email' => 'manual-default-funding@example.test',
        'mobile' => '639171234568',
        'password' => 'password',
    ]);

    app(ProviderAccountLinkRepositoryContract::class)->storeFromProvisioningResult($owner, [
        'provider' => 'paynamics',
        'topology' => 'provider_customer_wallet',
        'mode' => ProviderProvisioningMode::WalletResolve->value,
        'status' => 'ready',
        'provider_wallet_id' => 'CNSTWLLTFUNDINGMANUAL01',
    ]);

    EmiWallet::unguarded(function () use ($owner): void {
        EmiWallet::query()->create([
            'holder_type' => $owner::class,
            'holder_id' => $owner->getKey(),
            'name' => 'Manual Default Funding Wallet',
            'slug' => 'paynamics-cnstwlltfundingmanual01',
            'uuid' => (string) Str::uuid(),
            'description' => 'Provider wallet projection',
            'balance' => 0,
            'decimal_places' => 2,
            'provider_code' => ProviderCode::PaynamicsConstellation->value,
            'provider_wallet_id' => 'CNSTWLLTFUNDINGMANUAL01',
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

    $paynamicsBalances = Mockery::mock(SyncPaynamicsWalletBalance::class);
    $paynamicsBalances->shouldReceive('handle')
        ->once()
        ->with('CNSTWLLTFUNDINGMANUAL01', $owner)
        ->andReturn([
            'wallet' => EmiWallet::query()->where('provider_wallet_id', 'CNSTWLLTFUNDINGMANUAL01')->firstOrFail(),
            'response' => [],
            'balance_minor' => 8030,
            'balance' => 80.30,
            'currency' => 'PHP',
        ]);

    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets->shouldNotReceive('getBalance');

    $decision = (new ProviderAwareFundingPolicy(
        $settings,
        app(ProviderAccountLinkRepositoryContract::class),
        $provisioning,
        $wallets,
        $paynamicsBalances,
    ))->assertCanIssue($owner, (object) ['balance' => 86300], 50.00);

    expect($decision->allowed)->toBeTrue()
        ->and($decision->authority)->toBe('provider_wallet')
        ->and($decision->available_minor)->toBe(8030)
        ->and($decision->required_minor)->toBe(5000);
});
