<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Services\Claim\OnboardingSuccessActionResolver;

beforeEach(function (): void {
    Route::get('/x/cockpit', fn () => response('cockpit'))
        ->name('x-change.cockpit.entry');
    Route::get('/x/cockpit/overview', fn () => response('overview'))
        ->name('x-change.cockpit.dashboard');
    Route::get('/x/cockpit/quick-generate', fn () => response('quick-generate'))
        ->name('x-change.cockpit.quick-generate');
});

it('resolves maker onboarding success to the issuance workspace action', function (): void {
    $action = app(OnboardingSuccessActionResolver::class)->resolve([
        'primary_action_intent' => 'enter_workspace',
        'primary_action_role' => 'Maker',
    ]);

    expect($action)->toMatchArray([
        'key' => 'x-change.onboarding-success.enter-workspace',
        'label' => 'Go to my workspace',
        'intent' => 'enter_workspace',
        'enabled' => true,
        'source' => 'x-action',
        'target' => [
            'type' => 'route',
            'url' => route('x-change.cockpit.quick-generate'),
            'method' => 'GET',
            'redirectable' => true,
            'external' => false,
        ],
    ]);
});

it('resolves checker onboarding success to the overview workspace action', function (): void {
    $action = app(OnboardingSuccessActionResolver::class)->resolve([
        'primary_action_intent' => 'enter_workspace',
        'primary_action_role' => 'Checker',
    ]);

    expect($action)->toMatchArray([
        'label' => 'Go to my workspace',
        'target' => [
            'type' => 'route',
            'url' => route('x-change.cockpit.dashboard'),
            'method' => 'GET',
            'redirectable' => true,
            'external' => false,
        ],
    ]);
});

it('does not resolve unrelated action intents', function (): void {
    expect(app(OnboardingSuccessActionResolver::class)->resolve([
        'primary_action_intent' => 'claim_again',
    ]))->toBeNull();
});
