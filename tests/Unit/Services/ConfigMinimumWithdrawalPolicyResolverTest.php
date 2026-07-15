<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Services\ConfigMinimumWithdrawalPolicyResolver;

it('resolves netbank below the issuer default to the issuer default minimum', function () {
    config()->set('x-change.minimum_withdrawal.default', 25.00);
    config()->set('x-change.minimum_withdrawal.providers.netbank.PHP', 5.00);

    $policy = app(ConfigMinimumWithdrawalPolicyResolver::class)->resolve([
        'provider' => 'netbank',
        'cash' => [
            'currency' => 'PHP',
        ],
    ]);

    expect($policy->provider_minimum)->toBe(5.0)
        ->and($policy->issuer_default_minimum)->toBe(25.0)
        ->and($policy->effective_minimum)->toBe(25.0)
        ->and($policy->source)->toBe('issuer_default');
});

it('resolves paynamics above the issuer default to the provider minimum', function () {
    config()->set('x-change.minimum_withdrawal.default', 25.00);
    config()->set('x-change.minimum_withdrawal.providers.paynamics.PHP', 50.00);

    $policy = app(ConfigMinimumWithdrawalPolicyResolver::class)->resolve([
        'provider' => 'paynamics',
        'cash' => [
            'currency' => 'PHP',
        ],
    ]);

    expect($policy->provider_minimum)->toBe(50.0)
        ->and($policy->issuer_default_minimum)->toBe(25.0)
        ->and($policy->effective_minimum)->toBe(50.0)
        ->and($policy->source)->toBe('provider');
});

it('allows the operator minimum to raise the effective minimum', function () {
    config()->set('x-change.minimum_withdrawal.default', 25.00);
    config()->set('x-change.minimum_withdrawal.providers.netbank.PHP', 5.00);

    $policy = app(ConfigMinimumWithdrawalPolicyResolver::class)->resolve([
        'provider' => 'netbank',
        'cash' => [
            'currency' => 'PHP',
            'min_withdrawal' => 100,
        ],
    ]);

    expect($policy->operator_minimum)->toBe(100.0)
        ->and($policy->effective_minimum)->toBe(100.0)
        ->and($policy->source)->toBe('operator');
});

it('rejects a minimum withdrawal below the effective floor', function () {
    config()->set('x-change.minimum_withdrawal.default', 25.00);
    config()->set('x-change.minimum_withdrawal.providers.paynamics.PHP', 50.00);

    app(ConfigMinimumWithdrawalPolicyResolver::class)->assertIssuancePayload([
        'provider' => 'paynamics',
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'slice_mode' => 'open',
            'max_slices' => 2,
            'min_withdrawal' => 25,
        ],
    ]);
})->throws(ValidationException::class, 'Minimum withdrawal must be at least PHP 50.00.');

it('rejects fixed slices below the effective minimum', function () {
    config()->set('x-change.minimum_withdrawal.default', 25.00);

    app(ConfigMinimumWithdrawalPolicyResolver::class)->assertIssuancePayload([
        'provider' => 'manual',
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'slice_mode' => 'fixed',
            'slices' => 5,
        ],
    ]);
})->throws(ValidationException::class, '5 slices would create PHP 20.00 claims, below the PHP 25.00 minimum withdrawal.');

it('rejects named slices below the effective minimum', function () {
    config()->set('x-change.minimum_withdrawal.default', 25.00);

    app(ConfigMinimumWithdrawalPolicyResolver::class)->assertIssuancePayload([
        'provider' => 'manual',
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
        ],
        'metadata' => [
            'slices' => [
                ['amount' => 80],
                ['amount' => 20],
            ],
        ],
    ]);
})->throws(ValidationException::class, 'Named slice amount must be at least PHP 25.00.');
