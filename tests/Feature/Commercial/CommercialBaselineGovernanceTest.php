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
use LBHurtado\XChange\Services\Commercial\ActivateCommercialOffering;
use LBHurtado\XChange\Services\Commercial\CommercialGovernanceInspector;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Services\Configuration\CommissioningManifestRecorder;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
});

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

it('replays baseline journal evidence after a governed revision retires the baseline', function (): void {
    $service = app(ProvisionCommercialBaselines::class);
    $service->provision('commissioning-manifest:first');
    $baseline = CommercialOffering::query()->where('profile', 'pay_code')->sole();
    $governed = CommercialOffering::query()->create([
        ...$baseline->only([
            'reference',
            'profile',
            'currency',
            'snapshot_hash',
            'snapshot',
            'manifest_schema',
            'manifest_hash',
            'manifest_yaml',
            'effective_at',
        ]),
        'version' => 2,
        'status' => 'published',
        'origin' => 'maker_checker_revision',
        'authorization_reference' => 'pricing-approval:v2',
    ]);

    app(ActivateCommercialOffering::class)->execute(
        $governed,
        CommercialActivationAuthority::IndependentApproval,
        'commercial-activation:v2',
    );

    expect($baseline->refresh()->status->value)->toBe('retired')
        ->and(fn () => $service->provision('commissioning-manifest:second'))->not->toThrow(Throwable::class)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'commercial.offering.baseline_provisioned')
            ->count())->toBe(2);
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

    expect($status['partners'])->toMatchArray([
        'storage_ready' => true,
        'active_count' => 0,
        'pending_partner_count' => 0,
        'pending_destination_count' => 0,
    ])->and($status['operations'])->toMatchArray([
        'storage_ready' => true,
        'live_provider_calls_enabled' => false,
        'queue' => 'x-change-funding',
        'provider_cost_review_count' => 0,
        'open_commission_payout_count' => 0,
    ]);

    expect($status['operational'])->toBeTrue()
        ->and($status['issuance_available'])->toBeTrue()
        ->and($status['component_economics'])->toMatchArray([
            'operational' => true,
            'complete_profile_count' => 2,
            'required_profile_count' => 2,
        ])
        ->and($status['recipient_designations'])->toMatchArray([
            'operational' => true,
            'required_count' => 1,
            'active_count' => 1,
        ])
        ->and($status['recognition_policies'])->toMatchArray([
            'operational' => true,
            'required_count' => 1,
            'ready_count' => 1,
        ])
        ->and(data_get($status, 'recognition_policies.policies.0'))->toMatchArray([
            'reference' => 'recognition:pay-code-issuance:v1',
            'version' => 1,
            'billable_event_reference' => 'pay_code.issued_with_component',
            'trigger' => 'commercial_sale.accepted',
            'timing' => 'immediate',
            'ready' => true,
        ])
        ->and($status['changes_locked'])->toBeTrue()
        ->and($status['governance_ready'])->toBeFalse()
        ->and($status['state'])->toBe('baseline_active_changes_locked')
        ->and($status['profiles'])->toHaveCount(2);

    $this->artisan('x-change:commercial:governance-status', ['--json' => true])
        ->expectsOutputToContain('"state": "baseline_active_changes_locked"')
        ->assertSuccessful();

    $this->artisan('x-change:doctor', ['--json' => true])
        ->expectsOutputToContain('"name": "commercial component economics"')
        ->assertSuccessful();

    $this->artisan('x-change:doctor', ['--json' => true])
        ->expectsOutputToContain('"name": "commercial recognition policies"')
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
