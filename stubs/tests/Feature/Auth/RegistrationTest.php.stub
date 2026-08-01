<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'mobile' => '09171234567',
        'email' => '',
        'password' => '1234',
        'password_confirmation' => '1234',
    ]);

    $this->assertAuthenticated();
    $verificationRequired = (bool) config(
        'x-change.onboarding.mobile_verification.enabled',
        true,
    );
    $response->assertRedirect($verificationRequired
        ? route('x-change.onboarding.mobile-verification.show')
        : '/x/cockpit');

    $this->assertDatabaseHas('users', [
        'name' => 'Test User',
        'mobile' => '639171234567',
        'email' => null,
    ]);

    if (! $verificationRequired) {
        expect(auth()->user()->mobile_verified_at)->not->toBeNull();
    }
});
