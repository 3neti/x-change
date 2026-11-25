<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Services\Banking\BankAccountIdentityRegistry;

it('reuses one normalized identity for the same owner', function () {
    $owner = actingAsTestUser();
    $registry = app(BankAccountIdentityRegistry::class);
    $first = $registry->claim($owner, 'netbank', '991-100-00123-4');
    $second = $registry->claim($owner, 'netbank', '991100001234');

    expect($first->is($second))->toBeTrue();
});

it('rejects a normalized identity already claimed by another owner', function () {
    $first = actingAsTestUser();
    $second = actingAsTestUser();
    $registry = app(BankAccountIdentityRegistry::class);
    $registry->claim($first, 'netbank', '991-100-00123-4');

    expect(fn () => $registry->claim($second, 'netbank', '991100001234'))
        ->toThrow(ValidationException::class);
});

it('stops account identity writes if the identity key changes', function () {
    $owner = actingAsTestUser();
    $registry = app(BankAccountIdentityRegistry::class);
    $registry->claim($owner, 'netbank', '991100001234');
    config()->set('app.key', 'a-different-identity-key');

    expect(fn () => $registry->claim($owner, 'netbank', '991100001234'))
        ->toThrow(ValidationException::class, 'identity key changed');
});

it('rejects an unavailable identity key', function () {
    $owner = actingAsTestUser();
    config()->set('app.key', '');

    expect(fn () => app(BankAccountIdentityRegistry::class)->claim($owner, 'netbank', '991100001234'))
        ->toThrow(ValidationException::class, 'identity key is unavailable');
});
