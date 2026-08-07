<?php

declare(strict_types=1);

use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
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
        ->assertJsonPath('props.commercial_offering.controls.commissions.earned_minor', 0)
        ->assertJsonPath('props.commercial_offering.controls.policy.commission_requires_attributed_participant', true)
        ->assertJsonPath('props.xchange.navigation.commercial_controls_visible', true)
        ->assertJsonPath('props.commercial_offering.can_manage', false)
        ->assertJsonPath('props.commercial_offering.can_approve', false);
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
