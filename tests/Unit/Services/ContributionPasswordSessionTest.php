<?php

declare(strict_types=1);

use Illuminate\Contracts\Session\Session;
use LBHurtado\SettlementEnvelope\Models\EnvelopeContributionToken;
use LBHurtado\XChange\Services\Settlement\ContributionPasswordSession;

it('binds password verification to a single token and invalidates it when revoked', function (): void {
    $first = new EnvelopeContributionToken(['expires_at' => now()->addHour(), 'password' => 'password-one']);
    $first->id = 1;
    $first->exists = true;
    $second = new EnvelopeContributionToken(['expires_at' => now()->addHour(), 'password' => 'password-two']);
    $second->id = 2;
    $second->exists = true;
    $session = app(Session::class);
    $service = new ContributionPasswordSession;

    expect($service->verify($first, 'wrong', $session))->toBeFalse()
        ->and($service->isVerified($first, $session))->toBeFalse()
        ->and($service->verify($first, 'password-one', $session))->toBeTrue()
        ->and($service->isVerified($first, $session))->toBeTrue()
        ->and($service->isVerified($second, $session))->toBeFalse();
    $first->revoked_at = now();
    expect($service->isVerified($first, $session))->toBeFalse();
});

it('invalidates a session after a password is changed', function (): void {
    $token = new EnvelopeContributionToken(['expires_at' => now()->addHour(), 'password' => 'old-password']);
    $token->id = 3;
    $token->exists = true;
    $session = app(Session::class);
    $service = new ContributionPasswordSession;

    expect($service->verify($token, 'old-password', $session))->toBeTrue();
    $token->password = 'new-password';
    expect($service->isVerified($token, $session))->toBeFalse();
});
