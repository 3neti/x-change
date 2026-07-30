<?php

declare(strict_types=1);

use LBHurtado\XChange\Tests\Fakes\User;

it('treats the legacy x-change dashboard as a Cockpit compatibility route', function () {
    $user = User::query()->create([
        'name' => 'Returning Account Holder',
        'email' => 'returning@example.test',
        'password' => 'not-used',
    ]);

    $this->actingAs($user)
        ->get(route('x-change.dashboard'))
        ->assertRedirect(route('x-change.cockpit.dashboard'));
});

it('configures the Cockpit as the successful authentication home', function () {
    expect(config('fortify.home'))->toBe('/x/cockpit');
});

it('publishes an x-change landing page with canonical Wayfinder destinations', function () {
    $stub = file_get_contents(
        dirname(__DIR__, 3).'/stubs/resources/js/pages/Welcome.vue.stub',
    );

    expect($stub)
        ->toContain("from '@/routes/x-change/cockpit'")
        ->toContain(':href="dashboard()"')
        ->toContain('Settlement Operating System')
        ->not->toContain('laravel.com/docs')
        ->not->toContain('/x/dashboard');
});
