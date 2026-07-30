<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Route;
use Inertia\Response as InertiaResponse;
use LBHurtado\XChange\Http\Controllers\Web\Onboarding\InitialPinSetupController;
use LBHurtado\XChange\Http\Middleware\RequireInitialPinSetup;
use LBHurtado\XChange\Services\Onboarding\AccountPinSetupState;
use LBHurtado\XChange\Tests\Fakes\User;

it('requires authentication to create an initial PIN', function (): void {
    expect(
        app('router')
            ->getRoutes()
            ->getByName('x-change.onboarding.pin.show')
            ?->gatherMiddleware(),
    )->toContain('auth');
});

it('shows initial PIN setup only for an onboarding Account that needs it', function (): void {
    $user = actingAsTestUser();
    $pinSetup = app(AccountPinSetupState::class);
    $pinSetup->markRequired($user);
    $request = Request::create('/x/onboarding/pin');
    $request->setUserResolver(fn (): User => $user);

    $response = app(InitialPinSetupController::class)->show(
        $request,
        $pinSetup,
    );

    expect($response)->toBeInstanceOf(InertiaResponse::class);

    $pinSetup->markCompleted($user);
    $response = app(InitialPinSetupController::class)->show(
        $request,
        $pinSetup,
    );

    expect($response->getTargetUrl())
        ->toBe(route('x-change.cockpit.dashboard'));
});

it('keeps an onboarding Account out of Cockpit until its PIN is created', function (): void {
    $user = actingAsTestUser();
    $pinSetup = app(AccountPinSetupState::class);
    $pinSetup->markRequired($user);
    $request = Request::create('/x/cockpit');
    $request->setUserResolver(fn (): User => $user);
    $route = new Route(['GET'], '/x/cockpit', fn (): null => null);
    $route->name('x-change.cockpit.dashboard');
    $request->setRouteResolver(fn (): Route => $route);

    $response = (new RequireInitialPinSetup($pinSetup))->handle(
        $request,
        fn (): HttpResponse => response('passed'),
    );

    expect($response->getTargetUrl())
        ->toBe(route('x-change.onboarding.pin.show'));
});

it('rejects a non-numeric or unconfirmed initial PIN', function (): void {
    $user = actingAsTestUser();
    app(AccountPinSetupState::class)->markRequired($user);

    $this->from(route('x-change.onboarding.pin.show'))
        ->put(route('x-change.onboarding.pin.store'), [
            'password' => 'abcd',
            'password_confirmation' => 'dcba',
        ])
        ->assertRedirect(route('x-change.onboarding.pin.show'))
        ->assertSessionHasErrors(['password']);

    expect(Hash::check('abcd', (string) $user->fresh()->getAuthPassword()))
        ->toBeFalse()
        ->and(app(AccountPinSetupState::class)->isRequired($user->fresh()))
        ->toBeTrue();
});

it('creates the first PIN without asking for the generated credential', function (): void {
    $user = User::query()->create([
        'name' => 'Sofia Hurtado',
        'email' => 'sofia@hurtado.ph',
        'mobile' => '639399236237',
        'password' => Hash::make('unusable-random-credential'),
    ]);
    $pinSetup = app(AccountPinSetupState::class);
    $pinSetup->markRequired($user);

    $this->actingAs($user)
        ->put(route('x-change.onboarding.pin.store'), [
            'password' => '246810',
            'password_confirmation' => '246810',
        ])
        ->assertRedirect(route('x-change.cockpit.dashboard'))
        ->assertSessionHas(
            'status',
            'Your PIN is ready. Welcome to x-change.',
        );

    $fresh = $user->fresh();

    expect(Hash::check('246810', (string) $fresh->getAuthPassword()))
        ->toBeTrue()
        ->and(Hash::check(
            'unusable-random-credential',
            (string) $fresh->getAuthPassword(),
        ))->toBeFalse()
        ->and($pinSetup->isRequired($fresh))->toBeFalse()
        ->and(session('auth.password_confirmed_at'))->toBeInt();
});

it('does not let an established Account replace its PIN through initial setup', function (): void {
    $user = actingAsTestUser();
    $originalHash = (string) $user->getAuthPassword();

    $this->put(route('x-change.onboarding.pin.store'), [
        'password' => '246810',
        'password_confirmation' => '246810',
    ])->assertForbidden();

    expect((string) $user->fresh()->getAuthPassword())->toBe($originalHash);
});
