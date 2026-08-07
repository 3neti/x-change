<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialGovernanceMode;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOfferingActivation;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Services\Commercial\CommercialGovernanceInspector;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Services\Configuration\CommissioningManifestRecorder;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('commissions immutable baseline offerings without fabricating human approval', function (): void {
    config()->set(
        'x-change.commercial.offerings.governance_mode',
        CommercialGovernanceMode::BootstrapImmutable->value,
    );

    $activations = app(ProvisionCommercialBaselines::class)
        ->provision('commissioning-manifest:test-baseline');

    expect($activations)->toHaveCount(2)
        ->and(CommercialOffering::query()->count())->toBe(2)
        ->and(CommercialOfferingActivation::query()->count())->toBe(2);

    $payCode = CommercialOffering::query()->where('profile', 'pay_code')->sole();
    $activation = CommercialOfferingActivation::query()->where('profile', 'pay_code')->sole();

    expect($payCode->origin)->toBe(CommercialOfferingOrigin::InstallationBaseline)
        ->and($payCode->created_by_type)->toBeNull()
        ->and($payCode->approved_by_type)->toBeNull()
        ->and($payCode->source_package)->toBe('3neti/x-change')
        ->and($activation->authority)->toBe(CommercialActivationAuthority::CommissioningManifest)
        ->and(app(CommercialOfferingResolverContract::class)->resolve('pay_code')->snapshotHash())
        ->toBe($payCode->snapshot_hash)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'commercial.offering.baseline_provisioned')
            ->count())->toBe(2)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'commercial.offering.activated')
            ->count())->toBe(2);
});

it('reuses identical baseline and activation evidence idempotently', function (): void {
    $service = app(ProvisionCommercialBaselines::class);

    $first = $service->provision('commissioning-manifest:first');
    $second = $service->provision('commissioning-manifest:second');

    expect($second[0]->is($first[0]))->toBeTrue()
        ->and(CommercialOffering::query()->count())->toBe(2)
        ->and(CommercialOfferingActivation::query()->count())->toBe(2);
});

it('persists baselines without activating them in maker checker from start mode', function (): void {
    config()->set(
        'x-change.commercial.offerings.governance_mode',
        CommercialGovernanceMode::MakerCheckerFromStart->value,
    );

    $activations = app(ProvisionCommercialBaselines::class)
        ->provision('commissioning-manifest:strict');

    expect($activations)->toBe([])
        ->and(CommercialOffering::query()->count())->toBe(2)
        ->and(CommercialOfferingActivation::query()->count())->toBe(0);
});

it('refuses to overwrite a conflicting persisted baseline', function (): void {
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:first');

    CommercialOffering::query()->where('profile', 'pay_code')->update([
        'snapshot_hash' => str_repeat('a', 64),
    ]);

    expect(fn () => app(ProvisionCommercialBaselines::class)
        ->provision('commissioning-manifest:second'))
        ->toThrow(DomainException::class, 'conflicts with its persisted snapshot');
});

it('reports active baseline issuance while locking price changes', function (): void {
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:status');

    $status = app(CommercialGovernanceInspector::class)->inspect();

    expect($status['operational'])->toBeTrue()
        ->and($status['issuance_available'])->toBeTrue()
        ->and($status['changes_locked'])->toBeTrue()
        ->and($status['governance_ready'])->toBeFalse()
        ->and($status['state'])->toBe('baseline_active_changes_locked')
        ->and($status['profiles'])->toHaveCount(2);

    $this->artisan('x-change:commercial:governance-status', ['--json' => true])
        ->expectsOutputToContain('"state": "baseline_active_changes_locked"')
        ->assertSuccessful();
});

it('requires distinct non-system maker and checker authorities for changes', function (): void {
    $systemPrincipal = provisionTestSystemPrincipalForCommissioning();
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:roles');
    $maker = User::query()->create([
        'name' => 'Commercial Maker',
        'email' => 'commercial-maker@example.test',
        'password' => 'password',
    ]);
    $checker = User::query()->create([
        'name' => 'Commercial Checker',
        'email' => 'commercial-checker@example.test',
        'password' => 'password',
    ]);

    foreach ([
        [$systemPrincipal, CommercialOperatorCapability::ManageOfferings],
        [$maker, CommercialOperatorCapability::ManageOfferings],
        [$checker, CommercialOperatorCapability::ApproveOfferings],
    ] as [$operator, $capability]) {
        CommercialOperatorAuthorization::query()->create([
            'operator_type' => $operator->getMorphClass(),
            'operator_id' => $operator->getKey(),
            'capability' => $capability->value,
            'authorization_reference' => 'board-resolution:commercial-governance',
            'valid_from' => now(),
        ]);
    }

    $status = app(CommercialGovernanceInspector::class)->inspect();

    expect($status['roles'])->toMatchArray([
        'maker_count' => 1,
        'checker_count' => 1,
        'separation_ready' => true,
    ])->and($status['changes_locked'])->toBeFalse()
        ->and($status['governance_ready'])->toBeTrue()
        ->and($status['state'])->toBe('roles_ready');

    $this->artisan('x-change:doctor', [
        '--commercial-governance' => true,
        '--strict' => true,
        '--json' => true,
    ])->assertSuccessful();
});

it('provisions existing commissioned installations through an idempotent upgrade command', function (): void {
    provisionTestSystemPrincipalForCommissioning();
    app(CommissioningManifestRecorder::class)->record();

    $this->artisan('x-change:commercial:provision-baselines', ['--json' => true])
        ->expectsOutputToContain('"success": true')
        ->assertSuccessful();
    $this->artisan('x-change:commercial:provision-baselines', ['--json' => true])
        ->expectsOutputToContain('"success": true')
        ->assertSuccessful();

    expect(CommercialOffering::query()->count())->toBe(2)
        ->and(CommercialOfferingActivation::query()->count())->toBe(2)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'commercial.offering.baseline_provisioned')
            ->count())->toBe(2);
});
