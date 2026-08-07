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
    $packageRoot = dirname(__DIR__, 3);
    $stub = file_get_contents($packageRoot.'/stubs/resources/js/pages/Welcome.vue.stub');

    expect($stub)
        ->toContain("from '@/routes/x-change/cockpit'")
        ->toContain(':href="dashboard()"')
        ->toContain('Money should adapt to people.')
        ->toContain('Funds remain with a regulated bank or EMI provider.')
        ->toContain('PayCodeLogo')
        ->toContain('CockpitQuickGenerateOrderPresentation')
        ->toContain('CockpitClaimExperiencePreview')
        ->toContain('safe-presentation')
        ->toContain('autoplay')
        ->toContain('Get started')
        ->toContain('bank or EMI')
        ->not->toContain('laravel.com/docs')
        ->not->toContain('/vendor/x-change/images/logo-orange.png')
        ->not->toContain('/vendor/x-change/images/landing/cockpit-overview.png')
        ->not->toContain('/vendor/x-change/images/landing/claim-entry.png')
        ->not->toContain('Start with an Account')
        ->not->toContain('/x/dashboard')
        ->and(is_file($packageRoot.'/resources/js/cockpit/components/CockpitQuickGenerateOrderPresentation.vue'))->toBeTrue();
});
