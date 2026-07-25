<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use LBHurtado\Merchant\Contracts\MerchantProfileRepositoryContract;
use LBHurtado\XChange\Actions\Funding\UpdateFundingDestination;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\ProviderAccountLink;

it('publishes package-owned Accounts routes with verified and PIN-confirmed mutations', function () {
    $page = Route::getRoutes()->getByName('x-change.cockpit.accounts.index');
    $update = Route::getRoutes()->getByName(
        'x-change.cockpit.accounts.providers.funding-destination.update',
    );
    $scenario = Route::getRoutes()->getByName(
        'x-change.cockpit.accounts.scenarios.funding-destinations.store',
    );
    $merchantProfile = Route::getRoutes()->getByName(
        'x-change.cockpit.accounts.funding-qr-merchant-profile.update',
    );

    expect($page)->not->toBeNull()
        ->and($page->gatherMiddleware())->toContain('verified')
        ->and($update)->not->toBeNull()
        ->and($update->gatherMiddleware())->toContain('verified')
        ->and($update->gatherMiddleware())->toContain('password.confirm:settings.security.confirm')
        ->and($update->gatherMiddleware())->toContain('throttle:6,1')
        ->and($scenario)->not->toBeNull()
        ->and($scenario->gatherMiddleware())->toContain('verified')
        ->and($scenario->gatherMiddleware())->toContain('throttle:3,1')
        ->and($scenario->gatherMiddleware())->not->toContain('password.confirm:settings.security.confirm')
        ->and($merchantProfile)->not->toBeNull()
        ->and($merchantProfile->gatherMiddleware())->toContain('verified')
        ->and($merchantProfile->gatherMiddleware())
        ->toContain('password.confirm:settings.security.confirm');
});

it('renders the masked Accounts read model with encrypted Inertia history', function () {
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.accounts.index'));

    $response->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Accounts')
        ->assertJsonPath('props.account_read_model.status', 'available')
        ->assertJsonPath('props.account_read_model.providers.0.code', 'netbank')
        ->assertJsonPath('props.account_read_model.providers.0.mode', 'shared')
        ->assertJsonPath('props.account_read_model.providers.0.shared.display_reference', '•••• 0019 · VCA 91500')
        ->assertJsonPath('props.account_scenario.enabled', true)
        ->assertJsonPath('props.account_scenario.mode', 'rollback-only')
        ->assertJsonPath('props.funding_qr_merchant_profile.presentation_only', true)
        ->assertJsonPath('props.funding_qr_merchant_profile.controls_routing', false)
        ->assertJsonPath('props.funding_qr_merchant_profile.controls_settlement', false)
        ->assertJsonMissing(['113001000019', 'test-vca-alias-token']);

    expect($response->getContent())->toContain('"encryptHistory":true');
});

it('updates only the owner QR merchant presentation behind confirmation', function () {
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->patch(route(
            'x-change.cockpit.accounts.funding-qr-merchant-profile.update',
        ), [
            'name' => 'My Store',
            'city' => 'Makati',
            'merchant_category_code' => '5999',
            'merchant_name_template' => '{name} - {city}',
        ])
        ->assertRedirect()
        ->assertSessionHas(
            'funding_account_notice',
            'QR presentation updated. Funding is refreshing the reusable QR.',
        );

    $merchant = app(MerchantProfileRepositoryContract::class)->findForUser($owner);

    expect($merchant)->not->toBeNull()
        ->and($merchant->name)->toBe('My Store')
        ->and($merchant->city)->toBe('Makati')
        ->and($merchant->merchant_category_code)->toBe('5999')
        ->and($merchant->merchant_name_template)->toBe('{name} - {city}');
});

it('runs the protected Cockpit account-management walkthrough without durable state', function () {
    config()->set('x-change.cockpit.account_scenario.enabled', true);
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();
    config()->set('x-change.lifecycle.defaults.user_model', $owner::class);
    $balanceBefore = (int) $owner->wallet->balance;

    $response = $this->postJson(route(
        'x-change.cockpit.accounts.scenarios.funding-destinations.store',
    ));

    $response->assertOk()
        ->assertJsonPath('schema', 'x-change.lifecycle.account-management-scenario.v1')
        ->assertJsonPath('success', true)
        ->assertJsonPath('rollback_completed', true)
        ->assertJsonPath('simulation.provider_calls', 0)
        ->assertJsonPath('simulation.balance_changed', false)
        ->assertJsonCount(7, 'steps')
        ->assertJsonPath('steps.3.key', 'netbank_registration_token_boundary')
        ->assertJsonPath('steps.4.outcome', 'blocked')
        ->assertJsonMissing([
            '991100004242',
            'SCENARIO-DEMO-WALLET-654321',
            'scenario-write-only-netbank-token',
        ]);

    expect(FundingDestinationPreference::query()->count())->toBe(0)
        ->and(ProviderAccountLink::query()->count())->toBe(0)
        ->and((int) $owner->wallet->refresh()->balance)->toBe($balanceBefore);
});

it('refuses the Cockpit walkthrough when its environment gate is disabled', function () {
    config()->set('x-change.cockpit.account_scenario.enabled', false);
    $owner = actingAsTestUser();
    $owner->forceFill(['email_verified_at' => now()])->save();

    $this->postJson(route(
        'x-change.cockpit.accounts.scenarios.funding-destinations.store',
    ))->assertForbidden();
});

it('stores dedicated NetBank routing without generating or persisting a token', function () {
    $owner = actingAsTestUser();

    $preference = app(UpdateFundingDestination::class)->handle($owner, 'netbank', [
        'mode' => 'dedicated',
        'account_number' => '991100001234',
        'account_name' => 'Dedicated Treasury',
        'vca_alias' => '54321',
    ]);
    $link = $preference->providerAccountLink()->firstOrFail();
    $raw = DB::table('xchange_provider_account_links')->find($link->getKey());

    expect($preference->mode)->toBe('dedicated')
        ->and($link->verification_status)->toBe('routing_configured')
        ->and($link->display_reference)->toBe('•••• 1234 · VCA 54321')
        ->and($link->routing_profile_ciphertext)->not->toHaveKey('vca_alias_token')
        ->and($raw->routing_profile_ciphertext)->not->toContain('991100001234')
        ->and($raw->routing_profile_ciphertext)->not->toContain('vca_alias_token')
        ->and($this->fakeAuditLogger->hasEvent('funding.destination.selected'))->toBeTrue();
});

it('records a new routing configuration without treating token generation as enrollment', function () {
    $owner = actingAsTestUser();
    $update = app(UpdateFundingDestination::class);
    $data = [
        'mode' => 'dedicated',
        'account_number' => '991100001234',
        'account_name' => 'Dedicated Treasury',
        'vca_alias' => '54321',
    ];

    $first = $update->handle($owner, 'netbank', $data);
    $second = $update->handle($owner, 'netbank', $data);

    expect($first->providerAccountLink)->not->toBeNull()
        ->and($second->providerAccountLink)->not->toBeNull()
        ->and($second->providerAccountLink?->is($first->providerAccountLink))->toBeTrue()
        ->and(ProviderAccountLink::query()->count())->toBe(1)
        ->and(ProviderAccountLink::query()->get()->every(
            fn (ProviderAccountLink $link): bool => ! array_key_exists(
                'vca_alias_token',
                (array) $link->routing_profile_ciphertext,
            ),
        ))->toBeTrue();
});

it('returns a destination to shared mode without deleting its connection history', function () {
    $owner = actingAsTestUser();

    $preference = app(UpdateFundingDestination::class)->handle($owner, 'netbank', [
        'mode' => 'shared',
    ]);

    expect($preference->mode)->toBe('shared')
        ->and($preference->provider_account_link_id)->toBeNull()
        ->and(ProviderAccountLink::query()->count())->toBe(0);
});
