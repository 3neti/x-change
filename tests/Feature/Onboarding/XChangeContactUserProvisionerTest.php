<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\AccountProvisioningContract;
use LBHurtado\XChange\Data\Treasury\TreasuryAccountPortfolioData;
use LBHurtado\XChange\Services\Onboarding\AccountPinSetupState;
use LBHurtado\XChange\Services\Onboarding\OnboardingCredentialPolicy;
use LBHurtado\XChange\Services\Onboarding\XChangeContactUserProvisioner;
use LBHurtado\XChange\Tests\Fakes\User;

it('creates one Account and reuses it on an idempotent onboarding retry', function () {
    $accounts = Mockery::mock(AccountProvisioningContract::class);
    $accounts->shouldReceive('provision')
        ->twice()
        ->andReturn(new TreasuryAccountPortfolioData(
            principalReference: 'principal:account:onboarding',
            positions: [],
            skippedConnections: [],
        ));

    $pinSetup = app(AccountPinSetupState::class);
    $service = new XChangeContactUserProvisioner(
        $accounts,
        $pinSetup,
        app(OnboardingCredentialPolicy::class),
    );
    $contact = (object) ['mobile' => '09173011987'];
    $attributes = [
        'name' => 'Maria Santos',
        'email' => 'maria@example.test',
        'mobile_verified' => true,
    ];

    $created = $service->provision($contact, $attributes);
    $replayed = $service->provision($contact, $attributes);

    expect($created->promoted)->toBeTrue()
        ->and($created->meta['reused'])->toBeFalse()
        ->and($replayed->promoted)->toBeTrue()
        ->and($replayed->meta['reused'])->toBeTrue()
        ->and($created->user->is($replayed->user))->toBeTrue()
        ->and(User::query()->where('mobile', '639173011987')->count())->toBe(1)
        ->and($created->user->getAttribute('mobile_verified_at'))->not->toBeNull()
        ->and($pinSetup->isRequired($created->user->fresh()))->toBeTrue();
});

it('fails closed when an Email belongs to another Account', function () {
    User::query()->create([
        'name' => 'Existing User',
        'email' => 'taken@example.test',
        'mobile' => '639467438575',
        'password' => bcrypt('password'),
    ]);

    $accounts = Mockery::mock(AccountProvisioningContract::class);
    $accounts->shouldNotReceive('provision');

    $service = new XChangeContactUserProvisioner(
        $accounts,
        app(AccountPinSetupState::class),
        app(OnboardingCredentialPolicy::class),
    );

    expect(fn () => $service->provision(
        (object) ['mobile' => '09173011987'],
        [
            'name' => 'Maria Santos',
            'email' => 'taken@example.test',
            'mobile_verified' => true,
        ],
    ))->toThrow(RuntimeException::class, 'Email is already linked to another Account');
});

it('can temporarily omit invited Account PIN setup outside production', function (): void {
    config()->set('x-change.onboarding.voucher.require_pin_setup', false);
    $accounts = Mockery::mock(AccountProvisioningContract::class);
    $accounts->shouldReceive('provision')->once()->andReturn(
        new TreasuryAccountPortfolioData(
            principalReference: 'principal:account:frictionless',
            positions: [],
            skippedConnections: [],
        ),
    );
    $pinSetup = app(AccountPinSetupState::class);
    $service = new XChangeContactUserProvisioner(
        $accounts,
        $pinSetup,
        app(OnboardingCredentialPolicy::class),
    );

    $result = $service->provision(
        (object) ['mobile' => '09399236237'],
        [
            'name' => 'Sofia Hurtado',
            'email' => 'sofia@hurtado.ph',
            'mobile_verified' => true,
        ],
    );

    expect($pinSetup->isRequired($result->user->fresh()))->toBeFalse();
});
