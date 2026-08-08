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
        ->toContain("from '@/routes/x-change/claim'")
        ->toContain(':href="startClaim()"')
        ->toContain('Cashless disbursements')
        ->toContain('Money should adapt to people.')
        ->toContain('Receive a Pay Code. Send to the account you choose.')
        ->toContain('Claim when you’re ready—with a participating bank or')
        ->toContain('{{ $page.props.name }}')
        ->toContain('Powered by x-change')
        ->toContain('!h-18')
        ->toContain("background-image: url('/vendor/x-change/favicon.png')")
        ->toContain('bg-[length:auto_100%]')
        ->toContain('amount="₱537.00"')
        ->toContain('estimated-cost="₱543.90"')
        ->toContain('Open Cockpit')
        ->toContain('PayCodeLogo')
        ->toContain('CockpitQuickGenerateOrderPresentation')
        ->toContain('CockpitLandingClaimExperiencePresentation')
        ->toContain('Claim Pay Code')
        ->not->toContain('Pay Code disbursements')
        ->not->toContain('Create a controlled payout')
        ->not->toContain('Funds stay with your regulated bank or EMI provider.')
        ->not->toContain('laravel.com/docs')
        ->not->toContain('/vendor/x-change/images/logo-orange.png')
        ->not->toContain('/vendor/x-change/images/landing/cockpit-overview.png')
        ->not->toContain('/vendor/x-change/images/landing/claim-entry.png')
        ->not->toContain('Money that follows the moment')
        ->not->toContain('Funds remain with a regulated bank or EMI provider.')
        ->not->toContain('Start with an Account')
        ->not->toContain('/x/dashboard')
        ->and(is_file($packageRoot.'/resources/js/cockpit/components/CockpitQuickGenerateOrderPresentation.vue'))->toBeTrue()
        ->and(is_file($packageRoot.'/resources/js/cockpit/components/CockpitLandingClaimExperiencePresentation.vue'))->toBeTrue();

    expect(substr_count($stub, 'Open Cockpit'))->toBe(1);
});
