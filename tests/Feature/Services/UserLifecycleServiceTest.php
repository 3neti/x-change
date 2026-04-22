<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use LBHurtado\XChange\Services\UserLifecycleService;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function () {
    config()->set('x-change.onboarding.issuer_model', User::class);
});

it('creates a user through the lifecycle service', function () {
    $service = new UserLifecycleService();

    $result = $service->create([
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
        'mobile' => '09171234567',
        'country' => 'PH',
        'metadata' => [
            'source' => 'lifecycle',
        ],
    ]);

    expect($result)->toBeArray()
        ->and($result['id'])->not->toBeEmpty()
        ->and($result['name'])->toBe('Juan Dela Cruz')
        ->and($result['email'])->toBe('juan@example.com')
        ->and($result['mobile'])->toBe('639171234567')
        ->and($result['country'])->toBe('PH')
        ->and($result['status'])->toBe('created');

    $user = User::query()->findOrFail($result['id']);

    expect($user->name)->toBe('Juan Dela Cruz')
        ->and($user->email)->toBe('juan@example.com')
        ->and($user->country)->toBe('PH')
        ->and($user->getMobileChannel())->toBe('639171234567')
        ->and(data_get($user->metadata, 'profile.source'))->toBe('lifecycle');
});

it('shows a persisted user through the lifecycle service', function () {
    $user = User::query()->create([
        'name' => 'Maria Clara',
        'email' => 'maria@example.com',
        'password' => 'secret',
        'country' => 'PH',
        'metadata' => [],
    ]);

    $user->setMobileChannel('09179999999');

    $service = new UserLifecycleService();

    $result = $service->show((string) $user->getKey());

    expect($result)->toBeArray()
        ->and($result['id'])->toBe((string) $user->getKey())
        ->and($result['name'])->toBe('Maria Clara')
        ->and($result['email'])->toBe('maria@example.com')
        ->and($result['mobile'])->toBe('639179999999')
        ->and($result['country'])->toBe('PH')
        ->and($result['status'])->toBe('active');
});

it('throws when showing a missing user', function () {
    $service = new UserLifecycleService();

    expect(fn () => $service->show('999999'))
        ->toThrow(ModelNotFoundException::class);
});

it('submits kyc into lifecycle metadata', function () {
    $user = User::query()->create([
        'name' => 'KYC User',
        'email' => 'kyc@example.com',
        'password' => 'secret',
        'country' => 'PH',
        'metadata' => [],
    ]);

    $user->setMobileChannel('09170000000');

    $service = new UserLifecycleService();

    $result = $service->submitKyc((string) $user->getKey(), [
        'transaction_id' => 'KYC-001',
        'provider' => 'hyperverge',
        'status' => 'submitted',
        'metadata' => [
            'score' => 98,
        ],
    ]);

    expect($result)->toBeArray()
        ->and($result['user_id'])->toBe((string) $user->getKey())
        ->and($result['status'])->toBe('submitted')
        ->and($result['transaction_id'])->toBe('KYC-001')
        ->and($result['provider'])->toBe('hyperverge')
        ->and($result['messages'])->toBe(['KYC submitted successfully.']);

    $fresh = User::query()->findOrFail($user->getKey());

    expect(data_get($fresh->metadata, 'kyc.transaction_id'))->toBe('KYC-001')
        ->and(data_get($fresh->metadata, 'kyc.provider'))->toBe('hyperverge')
        ->and(data_get($fresh->metadata, 'kyc.status'))->toBe('submitted')
        ->and(data_get($fresh->metadata, 'kyc.metadata.score'))->toBe(98);
});

it('shows user kyc from lifecycle metadata', function () {
    $user = User::query()->create([
        'name' => 'Shown KYC User',
        'email' => 'show-kyc@example.com',
        'password' => 'secret',
        'country' => 'PH',
        'metadata' => [
            'kyc' => [
                'transaction_id' => 'KYC-ABC',
                'provider' => 'hyperverge',
                'status' => 'approved',
            ],
        ],
    ]);

    $user->setMobileChannel('09171111111');

    $service = new UserLifecycleService();

    $result = $service->showKyc((string) $user->getKey());

    expect($result)->toBeArray()
        ->and($result['user_id'])->toBe((string) $user->getKey())
        ->and($result['status'])->toBe('approved')
        ->and($result['transaction_id'])->toBe('KYC-ABC')
        ->and($result['provider'])->toBe('hyperverge')
        ->and($result['messages'])->toBe([]);
});

it('returns unknown kyc when user has no kyc metadata', function () {
    $user = User::query()->create([
        'name' => 'No KYC User',
        'email' => 'nokyc@example.com',
        'password' => 'secret',
        'country' => 'PH',
        'metadata' => [],
    ]);

    $user->setMobileChannel('09172222222');

    $service = new UserLifecycleService();

    $result = $service->showKyc((string) $user->getKey());

    expect($result)->toBeArray()
        ->and($result['user_id'])->toBe((string) $user->getKey())
        ->and($result['status'])->toBe('unknown')
        ->and($result['transaction_id'])->toBeNull()
        ->and($result['provider'])->toBeNull()
        ->and($result['messages'])->toBe([]);
});

it('throws when submitting kyc for a missing user', function () {
    $service = new UserLifecycleService();

    expect(fn () => $service->submitKyc('999999', [
        'transaction_id' => 'KYC-MISSING',
        'provider' => 'hyperverge',
    ]))->toThrow(ModelNotFoundException::class);
});
