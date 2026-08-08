<?php

declare(strict_types=1);

use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\CommercialPartnerRevisionStatus;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Models\CommercialPartnerRevision;
use LBHurtado\XChange\Models\CommercialProviderCostBatch;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;
use LBHurtado\XChange\Services\Commercial\BootstrapCommercialOfferingFactory;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Tests\Fakes\User;

function authorizeCommercialOperator(User $operator, CommercialOperatorCapability $capability): void
{
    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(),
        'operator_id' => $operator->getKey(),
        'capability' => $capability->value,
        'authorization_reference' => 'cockpit-test:'.$capability->value.':'.$operator->getKey(),
        'valid_from' => now()->subMinute(),
    ]);
}

function configureCockpitCommercialSystemPrincipal(): User
{
    $system = actingAsTestUser();
    config()->set('account.system_user.candidates', [
        'x-change' => [
            'model' => User::class,
            'identifier' => $system->email,
            'identifier_column' => 'email',
        ],
    ]);

    return $system;
}

beforeEach(function (): void {
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:cockpit-test');
});

/**
 * @return array<string, mixed>
 */
function commercialOfferingFormPayload(): array
{
    $offering = app(BootstrapCommercialOfferingFactory::class)->make('pay_code');

    return [
        'profile' => 'pay_code',
        'effective_at' => now()->subMinute()->toIso8601String(),
        'items' => array_map(
            static fn (array $item): array => [
                'reference' => $item['reference'],
                'unit_price' => number_format(((int) $item['unit_price_minor']) / 100, 2, '.', ''),
            ],
            $offering->catalog->toArray()['items'],
        ),
        'rules' => array_map(
            static fn (array $rule): array => [
                'reference' => $rule['reference'],
                'method' => $rule['line_type'] === 'residual'
                    ? 'residual'
                    : ($rule['basis_points'] !== null ? 'basis_points' : 'fixed'),
                'value' => $rule['basis_points']
                    ?? ($rule['fixed_amount_minor'] !== null
                        ? number_format(((int) $rule['fixed_amount_minor']) / 100, 2, '.', '')
                        : null),
                'minimum_amount' => null,
                'maximum_amount' => null,
                'recipient_reference' => $rule['recipient_reference'],
                'participant_role' => $rule['participant_role'],
            ],
            $offering->waterfallPolicy->toArray()['rules'],
        ),
    ];
}

it('hides Commercial Controls from ordinary account holders', function (): void {
    actingAsTestUser();

    $this->get(route('x-change.cockpit.commercial.index'))->assertNotFound();
});

it('shows the active governed offering to an authorized named operator', function (): void {
    $operator = actingAsTestUser();
    authorizeCommercialOperator($operator, CommercialOperatorCapability::ViewCommercialControls);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.commercial.index'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/CommercialOfferings')
        ->assertJsonPath('props.commercial_offering.source', 'installation_baseline')
        ->assertJsonPath('props.commercial_offering.active.reference', 'commercial-offering:pay_code')
        ->assertJsonPath('props.commercial_offering.active.legal_trace.jurisdiction', 'PH')
        ->assertJsonPath('props.commercial_offering.controls.schema', 'x-change.cockpit.commercial-controls.v1')
        ->assertJsonPath('props.commercial_offering.partners.schema', 'x-change.cockpit.commercial-partners.v1')
        ->assertJsonPath('props.commercial_offering.controls.commissions.earned_minor', 0)
        ->assertJsonPath('props.commercial_offering.controls.policy.commission_requires_attributed_participant', true)
        ->assertJsonPath('props.xchange.navigation.commercial_controls_visible', true)
        ->assertJsonPath('props.commercial_offering.can_manage', false)
        ->assertJsonPath('props.commercial_offering.can_approve', false);
});

it('submits and independently approves a Commercial Partner from the Cockpit', function (): void {
    configureCockpitCommercialSystemPrincipal();
    $maker = actingAsTestUser();
    authorizeCommercialOperator($maker, CommercialOperatorCapability::ManagePartners);

    $this->post(route('x-change.cockpit.commercial.partners.store'), [
        'reference' => 'partner:cockpit-test',
        'display_name' => 'Cockpit Partner',
        'legal_name' => 'Cockpit Partner Incorporated',
        'external_reference' => 'crm:cockpit-partner',
        'attribution_basis' => 'contractual_referral',
        'authorization_reference' => 'contract:cockpit-partner',
        'terms' => [
            'commission_basis' => 'fixed',
            'settlement_cycle' => 'monthly',
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $revision = CommercialPartnerRevision::query()->where('display_name', 'Cockpit Partner')->sole();
    expect($revision->status)->toBe(CommercialPartnerRevisionStatus::AwaitingApproval);

    $checker = actingAsTestUser();
    authorizeCommercialOperator($checker, CommercialOperatorCapability::ApprovePartners);
    $this->post(route(
        'x-change.cockpit.commercial.partner_revisions.approvals.store',
        $revision,
    ))->assertRedirect();

    expect($revision->refresh()->status)->toBe(CommercialPartnerRevisionStatus::Approved)
        ->and($revision->partner->status->value)->toBe('active');
});

it('rejects Commercial Partner mutations without matching authority', function (): void {
    actingAsTestUser();

    $this->post(route('x-change.cockpit.commercial.partners.store'), [
        'reference' => 'partner:unauthorized',
        'display_name' => 'Unauthorized Partner',
        'attribution_basis' => 'contractual_referral',
        'authorization_reference' => 'contract:unauthorized',
    ])->assertForbidden();
});

it('records provider cost evidence through an authorized fail-closed Cockpit control', function (): void {
    configureCockpitCommercialSystemPrincipal();
    $operator = actingAsTestUser();
    authorizeCommercialOperator($operator, CommercialOperatorCapability::ReconcileProviderCosts);

    $this->post(route('x-change.cockpit.commercial.provider_cost_batches.store'), [
        'reference' => 'provider-cost:cockpit:001',
        'provider' => 'netbank',
        'connection_reference' => 'netbank-primary',
        'currency' => 'PHP',
        'evidence_type' => 'provider_statement',
        'evidence_reference' => 'statement:cockpit:001',
        'observed_amount' => '1.00',
        'period_started_at' => now()->startOfMonth()->toDateString(),
        'period_ended_at' => now()->toDateString(),
        'observed_at' => now()->toDateString(),
        'idempotency_key' => 'provider-cost:cockpit:001',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $batch = CommercialProviderCostBatch::query()->sole();

    expect($batch->status->value)->toBe('review_required')
        ->and($batch->observed_amount_minor)->toBe(100)
        ->and($batch->lines()->count())->toBe(0);
});

it('keeps live commission submission inaccessible when the runtime gate is disabled', function (): void {
    configureCockpitCommercialSystemPrincipal();
    $operator = actingAsTestUser();
    authorizeCommercialOperator($operator, CommercialOperatorCapability::ExecuteCommissionPayouts);
    config()->set('x-change.commercial.operations.live_provider_calls_enabled', false);
    $batch = PartnerCommissionPayoutBatch::query()->create([
        'reference' => 'commission:cockpit:001',
        'partner_reference' => 'partner:cockpit',
        'provider' => 'netbank',
        'connection_reference' => 'netbank-primary',
        'position_reference' => 'position:commission:cockpit',
        'amount_minor' => 100,
        'currency' => 'PHP',
        'status' => 'approved',
        'destination' => ['bank_code' => 'GXCHPHM2XXX', 'account_number' => '09170000000'],
        'destination_hash' => hash('sha256', 'cockpit'),
        'destination_summary' => 'GCash · ••••0000',
        'request_idempotency_key' => 'commission:cockpit:001',
        'request_hash' => hash('sha256', 'request'),
        'maker_type' => User::class,
        'maker_id' => 100,
        'checker_type' => User::class,
        'checker_id' => 101,
        'approval_reference' => 'approval:cockpit:001',
        'metadata' => [],
        'period_started_at' => now()->startOfMonth(),
        'period_ended_at' => now(),
        'requested_at' => now(),
        'approved_at' => now(),
    ]);

    $this->post(route(
        'x-change.cockpit.commercial.commission_payout_batches.submissions.store',
        $batch,
    ), ['idempotency_key' => 'submission:cockpit:001'])->assertForbidden();

    expect($batch->refresh()->status->value)->toBe('approved')
        ->and($batch->attempts()->count())->toBe(0);
});

it('submits and independently publishes a new offering from the Cockpit', function (): void {
    $maker = actingAsTestUser();
    authorizeCommercialOperator($maker, CommercialOperatorCapability::ManageOfferings);

    $this->post(
        route('x-change.cockpit.commercial.offerings.store'),
        commercialOfferingFormPayload(),
    )->assertRedirect();

    $pending = CommercialOffering::query()
        ->where('status', CommercialOfferingStatus::PendingApproval->value)
        ->sole();

    expect($pending->status)->toBe(CommercialOfferingStatus::PendingApproval)
        ->and($pending->created_by_id)->toBe($maker->getKey());

    $checker = actingAsTestUser();
    authorizeCommercialOperator($checker, CommercialOperatorCapability::ApproveOfferings);

    $this->post(route('x-change.cockpit.commercial.offerings.approvals.store', $pending), [
        'authorization_reference' => 'delegated-pricing-control:2026-08-07:001',
    ])->assertRedirect();

    expect($pending->refresh()->status)->toBe(CommercialOfferingStatus::Published)
        ->and($pending->approved_by_id)->toBe($checker->getKey())
        ->and($pending->authorization_reference)->toBe('delegated-pricing-control:2026-08-07:001');

    $this->post(route('x-change.cockpit.commercial.offerings.activations.store', $pending), [
        'activation_reference' => 'deployment:commercial-offering:2026-08-07:001',
    ])->assertRedirect();

    expect($pending->currentActivation()->exists())->toBeTrue();
});

it('rejects Commercial Offering mutations without the matching authority', function (): void {
    $ordinaryUser = actingAsTestUser();

    $this->post(
        route('x-change.cockpit.commercial.offerings.store'),
        commercialOfferingFormPayload(),
    )->assertForbidden();

    $baseline = CommercialOffering::query()->where('profile', 'pay_code')->sole();

    $this->post(route('x-change.cockpit.commercial.offerings.approvals.store', $baseline), [
        'authorization_reference' => 'unauthorized',
    ])->assertForbidden();

    $this->post(route('x-change.cockpit.commercial.offerings.activations.store', $baseline), [
        'activation_reference' => 'unauthorized',
    ])->assertForbidden();

    expect($ordinaryUser)->not->toBeNull();
});
