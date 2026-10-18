<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOfferingActivation;

/**
 * Shared pricing workspace projection for operations and cockpit experiences.
 */
final readonly class CommercialPricingWorkspace
{
    public function __construct(
        private CommercialOfferingResolverContract $offerings,
        private CommercialOperatorAuthorityContract $authority,
        private CommercialControlReadModel $controls,
        private CommercialGovernanceInspector $governance,
        private CommercialPartnerReadModel $partners,
    ) {}

    /** @return array<string, mixed> */
    public function build(?Model $operator, string $profile = 'pay_code'): array
    {
        $profile = $profile === 'account_funding' ? 'account_funding' : 'pay_code';
        $offering = $this->offerings->resolve($profile);

        return [
            'profile' => $profile,
            'active' => $offering->toArray(),
            'source' => $this->source($profile),
            'artifact' => $this->artifact($profile),
            'history' => $this->history($profile),
            'can_manage' => $this->allows($operator, CommercialOperatorCapability::ManageOfferings),
            'can_manage_partners' => $this->allows($operator, CommercialOperatorCapability::ManagePartners),
            'can_approve_partners' => $this->allows($operator, CommercialOperatorCapability::ApprovePartners),
            'can_approve' => $this->allows($operator, CommercialOperatorCapability::ApproveOfferings),
            'can_reconcile_provider_costs' => $this->allows($operator, CommercialOperatorCapability::ReconcileProviderCosts),
            'can_request_commission_payouts' => $this->allows($operator, CommercialOperatorCapability::RequestCommissionPayouts),
            'can_approve_commission_payouts' => $this->allows($operator, CommercialOperatorCapability::ApproveCommissionPayouts),
            'can_execute_commission_payouts' => $this->allows($operator, CommercialOperatorCapability::ExecuteCommissionPayouts),
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
                    'maker' => ['type' => $pending->created_by_type, 'id' => $pending->created_by_id],
                ])->values()->all(),
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
                ])->values()->all(),
            'governance' => $this->governance->inspect(),
            'controls' => $this->controls->build($offering),
            'partners' => $this->partners->build(),
        ];
    }

    public function canView(?Model $operator): bool
    {
        foreach (CommercialOperatorCapability::cases() as $capability) {
            if ($this->allows($operator, $capability)) {
                return true;
            }
        }

        return false;
    }

    private function allows(?Model $operator, CommercialOperatorCapability $capability): bool
    {
        return $operator instanceof Model && $this->authority->allows($operator, $capability);
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
