<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\AccountProvisioningContract;
use LBHurtado\XChange\Data\Treasury\TreasuryAccountPortfolioData;
use LBHurtado\XChange\Services\Onboarding\AccountPinSetupState;
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
    $service = new XChangeContactUserProvisioner($accounts, $pinSetup);
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
