<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WalletAccessContract;

it('hydrates quick generate with operator-safe feedback defaults', function (): void {
    $user = actingAsTestUser();

    $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/QuickGenerate')
        ->assertJsonPath('props.feedback_defaults.schema', 'x-change.cockpit.quick-generate-feedback-defaults.v1')
        ->assertJsonPath('props.feedback_defaults.email', $user->email)
        ->assertJsonPath('props.feedback_defaults.mobile', null)
        ->assertJsonPath('props.feedback_defaults.source', 'authenticated-user')
        ->assertJsonPath('props.feedback_defaults.read_only', true)
        ->assertJsonPath('props.collection_destination.schema', 'x-change.cockpit.collection-destination.v1')
        ->assertJsonPath('props.collection_destination.label', 'Your Client Funds')
        ->assertJsonPath('props.collection_destination.authority', 'authenticated_operator')
        ->assertJsonPath('props.collection_destination.status', 'ready')
        ->assertJsonPath('props.collection_destination.editable', false)
        ->assertJsonPath('props.collection_destination.managed_automatically', true)
        ->assertJsonMissingPath('props.current_user_wallet_id')
        ->assertJsonMissingPath('props.feedback_defaults.raw_payload')
        ->assertJsonMissingPath('props.feedback_defaults.provider_payload')
        ->assertJsonMissingPath('props.feedback_defaults.wallet')
        ->assertJsonMissingPath('props.feedback_defaults.delivery_payload');

    $response = $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'));

    expect($response->json('props.feedback_defaults.webhook'))
        ->toStartWith(url('/x/webhooks/operator/'));
});

it('hydrates the platform wallet when the host default wallet is different', function (): void {
    $user = actingAsTestUser();
    $platformWallet = $user->wallet()->where('slug', 'platform')->firstOrFail();
    $legacyWallet = $user->wallet()->create([
        'name' => 'Legacy Default Wallet',
        'slug' => 'legacy-default',
    ]);
    $walletCount = $user->wallet()->getRelated()->newQuery()
        ->where('holder_type', $user->getMorphClass())
        ->where('holder_id', $user->getKey())
        ->count();

    config()->set('wallet.wallet.default.slug', 'legacy-default');
    $user->unsetRelation('wallet');

    expect($user->wallet->getKey())
        ->toBe($legacyWallet->getKey())
        ->not->toBe($platformWallet->getKey());

    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets
        ->shouldReceive('resolveForUser')
        ->atLeast()
        ->once()
        ->with(Mockery::on(fn (mixed $candidate): bool => $candidate === $user))
        ->andReturn($platformWallet);

    app()->instance(WalletAccessContract::class, $wallets);

    $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath('props.collection_destination.label', 'Your Client Funds')
        ->assertJsonPath('props.collection_destination.authority', 'authenticated_operator')
        ->assertJsonMissingPath('props.current_user_wallet_id');

    expect($user->wallet()->getRelated()->newQuery()
        ->where('holder_type', $user->getMorphClass())
        ->where('holder_id', $user->getKey())
        ->count()
    )->toBe($walletCount);
});

it('does not hide an unexpected platform wallet resolution failure', function (): void {
    actingAsTestUser();

    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets
        ->shouldReceive('resolveForUser')
        ->once()
        ->andThrow(new RuntimeException('Platform wallet lookup failed.'));

    app()->instance(WalletAccessContract::class, $wallets);

    $this->withoutExceptionHandling();

    expect(fn () => $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
    )->toThrow(RuntimeException::class, 'Platform wallet lookup failed.');
});

it('hydrates the effective onboarding OTP policy without exposing configuration details', function (): void {
    config()->set('x-change.onboarding.voucher.require_otp', false);

    actingAsTestUser();

    $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath('props.onboarding_policy.otp_required', false)
        ->assertJsonMissingPath('props.onboarding_policy.otp_secret')
        ->assertJsonMissingPath('props.onboarding_policy.provider');
});

it('hydrates sanitized instruction capability readiness', function (): void {
    config()->set('location-handler.opencage_api_key', 'open-cage-secret');
    config()->set('location-handler.map_provider', 'mapbox');
    config()->set('location-handler.mapbox_token', null);

    actingAsTestUser();

    $response = $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath('props.instruction_capabilities.location.status', 'unavailable')
        ->assertJsonPath('props.instruction_capabilities.location.issuance_allowed', false)
        ->assertJsonPath('props.instruction_capabilities.location.missing_configuration.0', 'MAPBOX_TOKEN');

    expect($response->getContent())
        ->not->toContain('open-cage-secret');
});

it('hydrates provider-owned settlement rail capabilities without sensitive configuration', function (): void {
    config()->set('x-change.provider_runtime.default_provider', 'netbank');
    config()->set('x-change.provider_runtime.providers.netbank.enabled', true);
    config()->set('omnipay.gateways.netbank.options.clientSecret', 'do-not-expose');
    config()->set('omnipay.gateways.netbank.options.rails', [
        'INSTAPAY' => [
            'enabled' => true,
            'min_amount' => 1,
            'max_amount' => 5_000_000,
            'fee' => 1_000,
        ],
        'PESONET' => [
            'enabled' => true,
            'min_amount' => 1,
            'max_amount' => 100_000_000,
            'fee' => 2_500,
        ],
    ]);

    actingAsTestUser();

    $response = $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath('props.settlement_rail_capabilities.provider.code', 'netbank')
        ->assertJsonPath('props.settlement_rail_capabilities.provider.label', 'NetBank')
        ->assertJsonPath('props.settlement_rail_capabilities.default_mode', 'automatic')
        ->assertJsonPath('props.settlement_rail_capabilities.rails.0.code', 'INSTAPAY')
        ->assertJsonPath('props.settlement_rail_capabilities.rails.0.provider_fee_minor', 1_000)
        ->assertJsonPath('props.settlement_rail_capabilities.live_provider_call', false);

    expect($response->getContent())->not->toContain('do-not-expose');
});
