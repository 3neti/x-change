<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Enums\CommercialGovernanceMode;
use LBHurtado\XChange\Enums\CommercialGovernanceState;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOfferingActivation;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use Throwable;

final readonly class CommercialGovernanceInspector
{
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

        $state = match (true) {
            ! $allProfilesActive => CommercialGovernanceState::ConfigurationInvalid,
            $publishedAwaitingActivation > 0 => CommercialGovernanceState::PublishedAwaitingActivation,
            $pendingApproval > 0 => CommercialGovernanceState::RevisionAwaitingApproval,
            $governedOfferingActive => CommercialGovernanceState::GovernedOfferingActive,
            $roleReadiness['separation_ready'] => CommercialGovernanceState::RolesReady,
            default => CommercialGovernanceState::BaselineActiveChangesLocked,
        };
        $operational = $allProfilesActive;

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
            'message' => $this->message($state),
        ];
    }

    private function schemaIsReady(): bool
    {
        try {
            return Schema::hasTable('x_change_commercial_offerings')
                && Schema::hasTable('x_change_commercial_offering_activations')
                && Schema::hasTable('x_change_commercial_operator_authorizations');
        } catch (Throwable) {
            return false;
        }
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
