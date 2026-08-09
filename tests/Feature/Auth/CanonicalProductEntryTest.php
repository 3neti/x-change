<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Inertia\Inertia;
use LBHurtado\XChange\Tests\Fakes\User;

it('shares the installed x-change version without host middleware', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $brandingMiddleware = file_get_contents(
        $packageRoot.'/src/Http/Middleware/ShareXChangeBranding.php',
    );

    expect(Inertia::getShared('xchange.version'))
        ->toBe(InstalledVersions::getPrettyVersion('3neti/x-change'))
        ->and($brandingMiddleware)
        ->not->toBeFalse()
        ->toContain("...(array) Inertia::getShared('xchange', [])");
});

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
        ->toContain('3neti/x-change {{ page.props.xchange.version }}')
        ->not->toContain('$page.props.version')
        ->toContain('© 2026 3neti R&amp;D OPC')
        ->toContain('mx-auto flex h-24 w-full max-w-[88rem]')
        ->toContain('mx-auto grid w-full max-w-[88rem]')
        ->toContain("import XChangeLogo from '@/components/x-change/XChangeLogo.vue'")
        ->toContain('sm:h-14')
        ->toContain('gClefPulleyBrandAssets.logo')
        ->toContain('bg-[length:auto_100%]')
        ->toContain('amount="₱537.00"')
        ->toContain('estimated-cost="₱543.90"')
        ->toContain('Open Cockpit')
        ->toContain('DRAFT')
        ->toContain('the instruction')
        ->toContain('ISSUE')
        ->toContain('the Pay Code')
        ->toContain('CLAIM')
        ->toContain('the payout')
        ->toContain('CockpitQuickGenerateOrderPresentation')
        ->toContain('CockpitLandingClaimExperiencePresentation')
        ->toContain('Claim Pay Code')
        ->not->toContain('Pay Code disbursements')
        ->not->toContain('Create a controlled payout')
        ->not->toContain('Funds stay with your regulated bank or EMI provider.')
        ->not->toContain('laravel.com/docs')
        ->not->toContain("import PayCodeLogo from '@/components/x-change/PayCodeLogo.vue'")
        ->not->toContain('Create the order')
        ->not->toContain('Issue the Pay Code')
        ->not->toContain('Recipient claims')
        ->not->toContain('/vendor/x-change/images/landing/cockpit-overview.png')
        ->not->toContain('/vendor/x-change/images/landing/claim-entry.png')
        ->not->toContain('Money that follows the moment')
        ->not->toContain('Funds remain with a regulated bank or EMI provider.')
        ->not->toContain('Start with an Account')
        ->not->toContain('/x/dashboard')
        ->and(is_file($packageRoot.'/resources/js/cockpit/components/CockpitQuickGenerateOrderPresentation.vue'))->toBeTrue()
        ->and(is_file($packageRoot.'/resources/js/cockpit/components/CockpitLandingClaimExperiencePresentation.vue'))->toBeTrue();

    expect(substr_count($stub, 'Open Cockpit'))->toBe(1);
    expect(substr_count($stub, '<XChangeLogo'))->toBe(1);
});

it('ships the canonical Pay Code vector alongside x-change branding', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $payCodeLogo = file_get_contents(
        $packageRoot.'/resources/assets/images/pay-code/pay-code-logo.svg',
    );
    $payCodeMark = file_get_contents(
        $packageRoot.'/resources/assets/images/pay-code/pay-code-mark.svg',
    );

    expect($payCodeLogo)->not->toBeFalse()
        ->toContain('viewBox="0 0 1254 1254"')
        ->toContain('#022A6E')
        ->toContain('#D8151B')
        ->toContain('The instruction that moves money.')
        ->and($payCodeMark)->not->toBeFalse()
        ->toContain('viewBox="245 70 765 765"')
        ->and(is_file($packageRoot.'/resources/assets/images/brand-library/x-change/svg/x-change-logo.svg'))->toBeTrue();
});
