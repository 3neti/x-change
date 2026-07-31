<?php

declare(strict_types=1);

use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleUserModelResolver;
use LBHurtado\XChange\Tests\Fakes\User;

it('resolves the lifecycle user through the configured auth guard provider', function (): void {
    config()->set('x-change.lifecycle.defaults.user_model');
    config()->set('auth.defaults.guard', 'operators');
    config()->set('auth.guards.operators.provider', 'cockpit_users');
    config()->set('auth.providers.cockpit_users.model', User::class);

    expect(app(LifecycleUserModelResolver::class)->resolve())->toBe(User::class);
});

it('preserves the explicit lifecycle user model override', function (): void {
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('auth.defaults.guard', 'missing');

    expect(app(LifecycleUserModelResolver::class)->resolve())->toBe(User::class);
});

it('fails when the configured auth provider cannot supply an eloquent model', function (): void {
    config()->set('x-change.lifecycle.defaults.user_model');
    config()->set('auth.defaults.guard', 'operators');
    config()->set('auth.guards.operators.provider', 'missing');

    app(LifecycleUserModelResolver::class)->resolve();
})->throws(RuntimeException::class, 'could not be resolved');
