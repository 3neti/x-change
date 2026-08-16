<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Contracts\CommercialComponentEconomicsResolverContract;
use LBHurtado\XChange\Contracts\CommercialRecipientDesignationResolverContract;
use LBHurtado\XChange\Enums\CommercialGovernanceMode;
use LBHurtado\XChange\Enums\CommercialGovernanceState;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialComponentEconomicsHead;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOfferingActivation;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Models\CommercialPartner;
use LBHurtado\XChange\Models\CommercialPartnerDestinationRevision;
use LBHurtado\XChange\Models\CommercialPartnerRevision;
use LBHurtado\XChange\Models\CommercialProviderCostBatch;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;
use Throwable;

final readonly class CommercialGovernanceInspector
{
    public function __construct(
        private CommercialComponentEconomicsResolverContract $componentEconomics,
        private CommercialRecipientDesignationResolverContract $recipientDesignations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $mode = CommercialGovernanceMode::tryFrom((string) config(
            'x-change.commercial.offerings.governance_mode',
            CommercialGovernanceMode::BootstrapImmutable->value,
        ));

        if ($mode === null || ! $this->schemaIsReady()) {
            return $this->invalid($mode, 'Commercial governance storage is not ready.');
        }

        $profiles = collect((array) config('x-change.commercial.offerings.profiles', []))
            ->filter(static fn (mixed $profile): bool => is_string($profile) && trim($profile) !== '')
            ->map(static fn (string $profile): string => trim($profile))
            ->unique()
            ->values();

        if ($profiles->isEmpty()) {
            return $this->invalid($mode, 'No governed Commercial Offering profiles are configured.');
        }

        $activations = CommercialOfferingActivation::query()
            ->with('offering')
            ->whereIn('profile', $profiles->all())
            ->whereNull('deactivated_at')
            ->get()
            ->keyBy('profile');
        $profileRows = $profiles->map(function (string $profile) use ($activations): array {
            /** @var CommercialOfferingActivation|null $activation */
            $activation = $activations->get($profile);

            return [
                'profile' => $profile,
                'active' => $activation !== null,
                'reference' => $activation?->offering_reference,
                'version' => $activation?->offering_version,
                'origin' => $activation?->origin?->value,
                'authority' => $activation?->authority?->value,
                'snapshot_hash' => $activation?->snapshot_hash,
                'source_package' => $activation?->source_package,
                'source_package_version' => $activation?->source_package_version,
                'activated_at' => $activation?->activated_at?->toIso8601String(),
            ];
        })->all();
        $roleReadiness = $this->roleReadiness();
        $pendingApproval = CommercialOffering::query()
            ->where('status', CommercialOfferingStatus::PendingApproval->value)
            ->count();
        $publishedAwaitingActivation = CommercialOffering::query()
            ->where('status', CommercialOfferingStatus::Published->value)
            ->whereDoesntHave('currentActivation')
            ->count();
        $allProfilesActive = collect($profileRows)->every(
            static fn (array $profile): bool => $profile['active'] === true,
        );
        $governedOfferingActive = collect($profileRows)->contains(
            static fn (array $profile): bool => $profile['origin'] === CommercialOfferingOrigin::MakerCheckerRevision->value,
        );
        $componentEconomics = $this->componentEconomicsReadiness($profiles->all());
        $recipientDesignations = $this->recipientDesignationReadiness($profiles->all());

        $state = match (true) {
            ! $allProfilesActive || ! $componentEconomics['operational'] || ! $recipientDesignations['operational'] => CommercialGovernanceState::ConfigurationInvalid,
            $publishedAwaitingActivation > 0 => CommercialGovernanceState::PublishedAwaitingActivation,
            $pendingApproval > 0 => CommercialGovernanceState::RevisionAwaitingApproval,
            $governedOfferingActive => CommercialGovernanceState::GovernedOfferingActive,
            $roleReadiness['separation_ready'] => CommercialGovernanceState::RolesReady,
            default => CommercialGovernanceState::BaselineActiveChangesLocked,
        };
        $operational = $allProfilesActive
            && $componentEconomics['operational']
            && $recipientDesignations['operational'];

        return [
            'schema' => 'x-change.commercial-governance-status.v1',
            'mode' => $mode->value,
            'state' => $state->value,
            'operational' => $operational,
            'issuance_available' => $operational,
            'changes_locked' => ! $roleReadiness['separation_ready'],
            'governance_ready' => $operational && $roleReadiness['separation_ready'],
            'roles' => $roleReadiness,
            'pending_approval_count' => $pendingApproval,
            'published_awaiting_activation_count' => $publishedAwaitingActivation,
            'profiles' => $profileRows,
            'component_economics' => $componentEconomics,
            'recipient_designations' => $recipientDesignations,
            'partners' => $this->partnerReadiness(),
            'operations' => $this->operationsReadiness(),
            'message' => $this->message($state),
        ];
    }

    private function schemaIsReady(): bool
    {
        try {
            return Schema::hasTable('x_change_commercial_offerings')
                && Schema::hasTable('x_change_commercial_offering_activations')
                && Schema::hasTable('x_change_commercial_operator_authorizations')
                && Schema::hasTable('x_change_commercial_component_economics_manifests')
                && Schema::hasTable('x_change_commercial_component_economics_activations')
                && Schema::hasTable('x_change_commercial_component_economics_heads')
                && Schema::hasTable('x_change_commercial_recipient_designations');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  list<string>  $profiles
     * @return array{operational: bool, required_count: int, active_count: int, designations: list<array<string, mixed>>, message: string}
     */
    private function recipientDesignationReadiness(array $profiles): array
    {
        $requirements = [];

        foreach ($profiles as $profile) {
            try {
                $economics = $this->componentEconomics->resolve($profile);
            } catch (Throwable) {
                continue;
            }

            foreach ($economics->components as $component) {
                foreach ($component->allocationSchedule?->rules ?? [] as $rule) {
                    if ($rule->designationReference === null) {
                        continue;
                    }

                    $requirements[$rule->designationReference] = [
                        'reference' => $rule->designationReference,
                        'counterparty_reference' => $rule->recipientReference,
                    ];
                }
            }
        }

        $rows = collect($requirements)->map(function (array $requirement): array {
            try {
                $designation = $this->recipientDesignations->resolve($requirement['reference']);

                return [
                    ...$requirement,
                    'active' => true,
                    'authority_hash' => $designation->authority_hash,
                    'origin' => $designation->origin,
                    'activated_at' => $designation->activated_at?->toIso8601String(),
                    'message' => 'Commercial Recipient Designation is active.',
                ];
            } catch (Throwable $exception) {
                return [
                    ...$requirement,
                    'active' => false,
                    'authority_hash' => null,
                    'origin' => null,
                    'activated_at' => null,
                    'message' => $exception->getMessage(),
                ];
            }
        })->values();
        $active = $rows->where('active', true)->count();
        $operational = $requirements !== [] && $active === count($requirements);

        return [
            'operational' => $operational,
            'required_count' => count($requirements),
            'active_count' => $active,
            'designations' => $rows->all(),
            'message' => $operational
                ? 'Every external component allocation has active recipient authority.'
                : 'Every external component allocation requires an active Commercial Recipient Designation.',
        ];
    }

    /**
     * @param  list<string>  $profiles
     * @return array{operational: bool, complete_profile_count: int, required_profile_count: int, profiles: list<array<string, mixed>>, message: string}
     */
    private function componentEconomicsReadiness(array $profiles): array
    {
        $rows = collect($profiles)->map(function (string $profile): array {
            $head = CommercialComponentEconomicsHead::query()
                ->with('currentActivation.economics')
                ->whereKey($profile)
                ->first();
            $activation = $head?->currentActivation;
            $economics = $activation?->economics;

            try {
                $this->componentEconomics->resolve($profile);
                $active = true;
                $message = 'Agreement Economics is active and bound to the current Commercial Offering.';
            } catch (Throwable $exception) {
                $active = false;
                $message = $exception->getMessage();
            }

            return [
                'profile' => $profile,
                'active' => $active,
                'reference' => $economics?->reference,
                'version' => $economics?->version,
                'manifest_hash' => $economics?->artifact_hash,
                'offering_snapshot_hash' => $economics?->offering_snapshot_hash,
                'activated_at' => $activation?->activated_at?->toIso8601String(),
                'message' => $message,
            ];
        })->values();
        $complete = $rows->where('active', true)->count();
        $operational = $profiles !== [] && $complete === count($profiles);

        return [
            'operational' => $operational,
            'complete_profile_count' => $complete,
            'required_profile_count' => count($profiles),
            'profiles' => $rows->all(),
            'message' => $operational
                ? 'Agreement Economics is active for every governed profile.'
                : 'Agreement Economics must be provisioned and bound to every active Commercial Offering.',
        ];
    }

    /** @return array<string, int|bool> */
    private function partnerReadiness(): array
    {
        $ready = Schema::hasTable('x_change_commercial_partners')
            && Schema::hasTable('x_change_commercial_partner_revisions')
            && Schema::hasTable('x_change_commercial_partner_destination_revisions');

        if (! $ready) {
            return [
                'storage_ready' => false,
                'active_count' => 0,
                'pending_partner_count' => 0,
                'pending_destination_count' => 0,
            ];
        }

        return [
            'storage_ready' => true,
            'active_count' => CommercialPartner::query()->active()->count(),
            'pending_partner_count' => CommercialPartnerRevision::query()
                ->where('status', 'awaiting_approval')->count(),
            'pending_destination_count' => CommercialPartnerDestinationRevision::query()
                ->where('status', 'awaiting_approval')->count(),
        ];
    }

    /** @return array<string, int|string|bool> */
    private function operationsReadiness(): array
    {
        $storageReady = Schema::hasTable('x_change_commercial_provider_cost_batches')
            && Schema::hasTable('x_change_partner_commission_payout_batches');

        return [
            'storage_ready' => $storageReady,
            'live_provider_calls_enabled' => (bool) config(
                'x-change.commercial.operations.live_provider_calls_enabled',
                false,
            ),
            'scheduled_reconciliation_enabled' => (bool) config(
                'x-change.commercial.operations.scheduled_reconciliation_enabled',
                true,
            ),
            'queue' => (string) config('x-change.commercial.operations.queue', 'x-change-funding'),
            'provider_cost_review_count' => $storageReady
                ? CommercialProviderCostBatch::query()->where('status', 'review_required')->count()
                : 0,
            'open_commission_payout_count' => $storageReady
                ? PartnerCommissionPayoutBatch::query()->whereIn('status', [
                    'awaiting_approval', 'approved', 'submitted', 'pending', 'suspense', 'rejected',
                ])->count()
                : 0,
        ];
    }

    /**
     * @return array{maker_count: int, checker_count: int, separation_ready: bool}
     */
    private function roleReadiness(): array
    {
        $systemPrincipal = $this->systemPrincipalIdentity();
        $authorizations = CommercialOperatorAuthorization::query()
            ->currentlyValid()
            ->whereIn('capability', [
                CommercialOperatorCapability::ManageOfferings->value,
                CommercialOperatorCapability::ApproveOfferings->value,
            ])
            ->get(['operator_type', 'operator_id', 'capability'])
            ->reject(function (CommercialOperatorAuthorization $authorization) use ($systemPrincipal): bool {
                return $systemPrincipal !== null
                    && $authorization->operator_type === $systemPrincipal['type']
                    && (string) $authorization->operator_id === $systemPrincipal['id'];
            });
        $makers = $authorizations
            ->where('capability', CommercialOperatorCapability::ManageOfferings->value)
            ->map(fn (CommercialOperatorAuthorization $authorization): string => $this->operatorKey($authorization))
            ->unique();
        $checkers = $authorizations
            ->where('capability', CommercialOperatorCapability::ApproveOfferings->value)
            ->map(fn (CommercialOperatorAuthorization $authorization): string => $this->operatorKey($authorization))
            ->unique();

        return [
            'maker_count' => $makers->count(),
            'checker_count' => $checkers->count(),
            'separation_ready' => $makers->diff($checkers)->isNotEmpty()
                && $checkers->diff($makers)->isNotEmpty(),
        ];
    }

    private function operatorKey(CommercialOperatorAuthorization $authorization): string
    {
        return $authorization->operator_type.':'.$authorization->operator_id;
    }

    /**
     * @return array{type: string, id: string}|null
     */
    private function systemPrincipalIdentity(): ?array
    {
        $modelClass = (string) config('x-change.onboarding.issuer_model');
        $column = trim((string) config('x-change.payout.system_user_column'));
        $identity = trim((string) config('x-change.payout.system_user_id'));

        if (! is_subclass_of($modelClass, Model::class) || $column === '' || $identity === '') {
            return null;
        }

        try {
            /** @var Model|null $principal */
            $principal = $modelClass::query()->where($column, $identity)->first();
        } catch (Throwable) {
            return null;
        }

        return $principal instanceof Model
            ? ['type' => $principal->getMorphClass(), 'id' => (string) $principal->getKey()]
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function invalid(?CommercialGovernanceMode $mode, string $message): array
    {
        return [
            'schema' => 'x-change.commercial-governance-status.v1',
            'mode' => $mode?->value,
            'state' => CommercialGovernanceState::ConfigurationInvalid->value,
            'operational' => false,
            'issuance_available' => false,
            'changes_locked' => true,
            'governance_ready' => false,
            'roles' => ['maker_count' => 0, 'checker_count' => 0, 'separation_ready' => false],
            'pending_approval_count' => 0,
            'published_awaiting_activation_count' => 0,
            'profiles' => [],
            'component_economics' => [
                'operational' => false,
                'complete_profile_count' => 0,
                'required_profile_count' => 0,
                'profiles' => [],
                'message' => 'Commercial Component Economics storage is not ready.',
            ],
            'recipient_designations' => [
                'operational' => false,
                'required_count' => 0,
                'active_count' => 0,
                'designations' => [],
                'message' => 'Commercial Recipient Designation storage is not ready.',
            ],
            'partners' => [
                'storage_ready' => false,
                'active_count' => 0,
                'pending_partner_count' => 0,
                'pending_destination_count' => 0,
            ],
            'operations' => [
                'storage_ready' => false,
                'live_provider_calls_enabled' => false,
                'scheduled_reconciliation_enabled' => false,
                'queue' => 'x-change-funding',
                'provider_cost_review_count' => 0,
                'open_commission_payout_count' => 0,
            ],
            'message' => $message,
        ];
    }

    private function message(CommercialGovernanceState $state): string
    {
        return match ($state) {
            CommercialGovernanceState::BaselineActiveChangesLocked => 'Initial package pricing is active. Price changes remain locked until independent maker and checker authorities exist.',
            CommercialGovernanceState::RolesReady => 'Initial package pricing remains active. Independent maker and checker authorities can now govern revisions.',
            CommercialGovernanceState::RevisionAwaitingApproval => 'A Commercial Offering revision is waiting for independent approval.',
            CommercialGovernanceState::PublishedAwaitingActivation => 'An approved Commercial Offering is published but not yet active.',
            CommercialGovernanceState::GovernedOfferingActive => 'A maker-checker governed Commercial Offering is active.',
            CommercialGovernanceState::ConfigurationInvalid => 'Commercial governance is not operational.',
        };
    }
}
