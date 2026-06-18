<?php

declare(strict_types=1);

use LBHurtado\XChange\Models\ProviderAccountLink;
use LBHurtado\XChange\Services\LinkExistingPaynamicsWallet;
use LBHurtado\XChange\Tests\Fakes\User;

it('links a Paynamics wallet from the authenticated profile surface', function () {
    $user = User::query()->create([
        'name' => 'Profile Wallet Owner',
        'email' => 'profile-wallet-owner@example.test',
        'mobile' => '639171234565',
        'password' => 'password',
    ]);

    $service = Mockery::mock(LinkExistingPaynamicsWallet::class);
    $service->shouldReceive('handle')
        ->once()
        ->with(Mockery::on(fn ($owner): bool => $owner->is($user)), 'CNSTWLLTPROFILE01')
        ->andReturn(new ProviderAccountLink);

    $this->app->instance(LinkExistingPaynamicsWallet::class, $service);

    $this->actingAs($user)
        ->post(route('x-change.provider-wallets.paynamics.store'), [
            'wallet_id' => 'CNSTWLLTPROFILE01',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'paynamics-wallet-linked');
});

it('rejects malformed Paynamics wallet IDs', function () {
    $user = User::query()->create([
        'name' => 'Malformed Wallet Owner',
        'email' => 'malformed-wallet-owner@example.test',
        'mobile' => '639171234566',
        'password' => 'password',
    ]);

    $service = Mockery::mock(LinkExistingPaynamicsWallet::class);
    $service->shouldNotReceive('handle');

    $this->app->instance(LinkExistingPaynamicsWallet::class, $service);

    $this->actingAs($user)
        ->from('/settings/profile')
        ->post(route('x-change.provider-wallets.paynamics.store'), [
            'wallet_id' => 'CNST WLLT PROFILE 01',
        ])
        ->assertRedirect('/settings/profile')
        ->assertSessionHasErrors('wallet_id');
});
