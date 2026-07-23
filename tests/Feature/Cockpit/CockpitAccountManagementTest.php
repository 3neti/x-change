<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use LBHurtado\PaymentGateway\Funding\NetbankFundingApiClient;
use LBHurtado\XChange\Actions\Funding\RotateNetbankFundingToken;
use LBHurtado\XChange\Actions\Funding\UpdateFundingDestination;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\ProviderAccountLink;

it('publishes package-owned Accounts routes with verified and PIN-confirmed mutations', function () {
    $page = Route::getRoutes()->getByName('x-change.cockpit.accounts.index');
    $update = Route::getRoutes()->getByName(
        'x-change.cockpit.accounts.providers.funding-destination.update',
    );

    expect($page)->not->toBeNull()
        ->and($page->gatherMiddleware())->toContain('verified')
        ->and($update)->not->toBeNull()
        ->and($update->gatherMiddleware())->toContain('verified')
        ->and($update->gatherMiddleware())->toContain('password.confirm:settings.security.confirm')
        ->and($update->gatherMiddleware())->toContain('throttle:6,1');
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
        ->assertJsonMissing(['113001000019', 'test-vca-alias-token']);

    expect($response->getContent())->toContain('"encryptHistory":true');
});

it('generates and stores a dedicated NetBank destination without exposing its token', function () {
    $owner = actingAsTestUser();
    $netbank = Mockery::mock(NetbankFundingApiClient::class);
    $netbank->shouldReceive('generateAliasToken')
        ->once()
        ->with('991100001234', '54321')
        ->andReturn('generated-write-only-token');
    $this->app->instance(NetbankFundingApiClient::class, $netbank);

    $preference = app(UpdateFundingDestination::class)->handle($owner, 'netbank', [
        'mode' => 'dedicated',
        'enrollment' => 'generate',
        'account_number' => '991100001234',
        'account_name' => 'Dedicated Treasury',
        'vca_alias' => '54321',
    ]);
    $link = $preference->providerAccountLink()->firstOrFail();
    $raw = DB::table('xchange_provider_account_links')->find($link->getKey());

    expect($preference->mode)->toBe('dedicated')
        ->and($link->verification_status)->toBe('verified')
        ->and($link->display_reference)->toBe('•••• 1234 · VCA 54321')
        ->and($link->routing_profile_ciphertext['vca_alias_token'])->toBe('generated-write-only-token')
        ->and($raw->routing_profile_ciphertext)->not->toContain('991100001234')
        ->and($raw->routing_profile_ciphertext)->not->toContain('generated-write-only-token')
        ->and($this->fakeAuditLogger->hasEvent('funding.destination.selected'))->toBeTrue();
});

it('does not regenerate an enrolled NetBank alias during an ordinary replacement', function () {
    $owner = actingAsTestUser();
    $netbank = Mockery::mock(NetbankFundingApiClient::class);
    $netbank->shouldReceive('generateAliasToken')->once()->andReturn('first-token');
    $this->app->instance(NetbankFundingApiClient::class, $netbank);
    $update = app(UpdateFundingDestination::class);
    $data = [
        'mode' => 'dedicated',
        'enrollment' => 'generate',
        'account_number' => '991100001234',
        'account_name' => 'Dedicated Treasury',
        'vca_alias' => '54321',
    ];

    $update->handle($owner, 'netbank', $data);

    expect(fn () => $update->handle($owner, 'netbank', $data))
        ->toThrow(
            ValidationException::class,
            'Use token rotation instead',
        );
});

it('rotates the token only through the explicit warned operation', function () {
    $owner = actingAsTestUser();
    $link = ProviderAccountLink::query()->create([
        'owner_type' => $owner::class,
        'owner_id' => $owner->getKey(),
        'provider' => 'netbank',
        'topology' => 'dedicated',
        'purpose' => 'funding',
        'mode' => 'bank_account_link',
        'status' => 'ready',
        'verification_status' => 'credential_supplied',
        'routing_profile_ciphertext' => [
            'bank_account_number' => '991100001234',
            'bank_account_name' => 'Dedicated Treasury',
            'vca_alias' => '54321',
            'vca_alias_token' => 'old-token',
        ],
        'display_reference' => '•••• 1234 · VCA 54321',
        'ready_at' => now(),
    ]);
    FundingDestinationPreference::query()->create([
        'owner_type' => $owner::class,
        'owner_id' => $owner->getKey(),
        'provider_code' => 'netbank',
        'mode' => 'dedicated',
        'provider_account_link_id' => $link->getKey(),
    ]);
    $netbank = Mockery::mock(NetbankFundingApiClient::class);
    $netbank->shouldReceive('generateAliasToken')
        ->once()
        ->with('991100001234', '54321')
        ->andReturn('rotated-token');
    $this->app->instance(NetbankFundingApiClient::class, $netbank);

    $rotated = app(RotateNetbankFundingToken::class)->handle($owner);

    expect($rotated->routing_profile_ciphertext['vca_alias_token'])->toBe('rotated-token')
        ->and($rotated->verification_status)->toBe('verified')
        ->and($this->fakeAuditLogger->hasEvent('funding.destination.token_rotated'))->toBeTrue();
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
