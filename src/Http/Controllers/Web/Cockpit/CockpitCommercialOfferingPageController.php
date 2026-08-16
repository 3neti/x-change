<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOfferingActivation;
use LBHurtado\XChange\Services\Commercial\CommercialControlReadModel;
use LBHurtado\XChange\Services\Commercial\CommercialGovernanceInspector;
use LBHurtado\XChange\Services\Commercial\CommercialPartnerReadModel;

final class CockpitCommercialOfferingPageController extends Controller
{
    public function __construct(
        private readonly CommercialOfferingResolverContract $offerings,
        private readonly CommercialOperatorAuthorityContract $authority,
        private readonly CommercialControlReadModel $controls,
        private readonly CommercialGovernanceInspector $governance,
        private readonly CommercialPartnerReadModel $partners,
    ) {}

    public function __invoke(Request $request): Response
    {
        $operator = $request->user();

        abort_unless($operator instanceof Model && $this->mayView($operator), 404);

        $profile = $request->string('profile')->value() === 'account_funding'
            ? 'account_funding'
            : 'pay_code';
        $offering = $this->offerings->resolve($profile);

        return Inertia::render('x-change/cockpit/CommercialOfferings', [
            'commercial_offering' => [
                'profile' => $profile,
                'active' => $offering->toArray(),
                'source' => $this->source($profile),
                'artifact' => $this->artifact($profile),
                'history' => $this->history($profile),
                'can_manage' => $this->authority->allows(
                    $operator,
                    CommercialOperatorCapability::ManageOfferings,
                ),
                'can_manage_partners' => $this->authority->allows(
                    $operator,
                    CommercialOperatorCapability::ManagePartners,
                ),
                'can_approve_partners' => $this->authority->allows(
                    $operator,
                    CommercialOperatorCapability::ApprovePartners,
                ),
                'can_approve' => $this->authority->allows(
                    $operator,
                    CommercialOperatorCapability::ApproveOfferings,
                ),
                'can_reconcile_provider_costs' => $this->authority->allows(
                    $operator,
                    CommercialOperatorCapability::ReconcileProviderCosts,
                ),
                'can_request_commission_payouts' => $this->authority->allows(
                    $operator,
                    CommercialOperatorCapability::RequestCommissionPayouts,
                ),
                'can_approve_commission_payouts' => $this->authority->allows(
                    $operator,
                    CommercialOperatorCapability::ApproveCommissionPayouts,
                ),
                'can_execute_commission_payouts' => $this->authority->allows(
                    $operator,
                    CommercialOperatorCapability::ExecuteCommissionPayouts,
                ),
                'pending' => CommercialOffering::query()
                    ->where('profile', $profile)
                    ->where('status', CommercialOfferingStatus::PendingApproval->value)
                    ->latest('submitted_at')
                    ->get()
                    ->map(fn (CommercialOffering $pending): array => [
                        'id' => $pending->getKey(),
                        'reference' => $pending->reference,
                        'version' => $pending->version,
                        'snapshot_hash' => $pending->snapshot_hash,
                        'effective_at' => $pending->effective_at?->toIso8601String(),
                        'submitted_at' => $pending->submitted_at?->toIso8601String(),
                        'maker' => [
                            'type' => $pending->created_by_type,
                            'id' => $pending->created_by_id,
                        ],
                    ])
                    ->values()
                    ->all(),
                'published' => CommercialOffering::query()
                    ->where('profile', $profile)
                    ->where('status', CommercialOfferingStatus::Published->value)
                    ->whereDoesntHave('currentActivation')
                    ->latest('approved_at')
                    ->get()
                    ->map(fn (CommercialOffering $published): array => [
                        'id' => $published->getKey(),
                        'reference' => $published->reference,
                        'version' => $published->version,
                        'snapshot_hash' => $published->snapshot_hash,
                        'effective_at' => $published->effective_at?->toIso8601String(),
                        'approved_at' => $published->approved_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
                'governance' => $this->governance->inspect(),
                'controls' => $this->controls->build($offering),
                'partners' => $this->partners->build(),
            ],
        ]);
    }

    private function mayView(Model $operator): bool
    {
        foreach (CommercialOperatorCapability::cases() as $capability) {
            if ($this->authority->allows($operator, $capability)) {
                return true;
            }
        }

        return false;
    }

    private function source(string $profile): string
    {
        $activation = CommercialOfferingActivation::query()
            ->where('profile', $profile)
            ->whereNull('deactivated_at')
            ->first();

        return $activation?->origin?->value ?? 'unavailable';
    }

    /** @return array<string, mixed>|null */
    private function artifact(string $profile): ?array
    {
        $activation = CommercialOfferingActivation::query()
            ->with('offering')
            ->where('profile', $profile)
            ->whereNull('deactivated_at')
            ->latest('activated_at')
            ->first();
        $offering = $activation?->offering;

        if (! $offering instanceof CommercialOffering) {
            return null;
        }

        return [
            'schema' => $offering->manifest_schema,
            'hash' => $offering->manifest_hash,
            'yaml' => $offering->manifest_yaml,
            'snapshot_hash' => $offering->snapshot_hash,
            'activation_reference' => $activation->activation_reference,
            'activated_at' => $activation->activated_at?->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function history(string $profile): array
    {
        return CommercialOffering::query()
            ->where('profile', $profile)
            ->orderByDesc('version')
            ->get()
            ->map(static fn (CommercialOffering $offering): array => [
                'reference' => $offering->reference,
                'version' => $offering->version,
                'status' => $offering->status->value,
                'origin' => $offering->origin->value,
                'snapshot_hash' => $offering->snapshot_hash,
                'manifest_hash' => $offering->manifest_hash,
                'effective_at' => $offering->effective_at?->toIso8601String(),
                'approved_at' => $offering->approved_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
