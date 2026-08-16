<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Provisioning;

use BackedEnum;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\PartnerApiOperatorCapability;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Models\PartnerApiOperatorAuthorization;
use LBHurtado\XChange\Models\ProvisioningOperatorAuthorization;
use LBHurtado\XChange\Models\TreasuryOperatorAuthorization;
use LBHurtado\XChange\Services\Commercial\ActivateCommercialRecipientDesignation;
use LBHurtado\XChange\Services\Commercial\CommercialGovernanceJournal;
use LBHurtado\XChange\Services\Commercial\RevokeCommercialRecipientDesignation;
use LBHurtado\XChange\Services\Commercial\TransitionCommercialRecipientDesignationEconomics;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiGovernanceJournal;
use LBHurtado\XProvisioning\Contracts\ProvisioningActivatorContract;
use LBHurtado\XProvisioning\Contracts\ProvisioningRevokerContract;
use LBHurtado\XProvisioning\Data\CommercialRecipientDesignationData;
use LBHurtado\XProvisioning\Enums\CommercialSettlementAccountBinding;
use LBHurtado\XProvisioning\Enums\ProvisioningProfile;
use LBHurtado\XProvisioning\Models\ProvisioningAcceptance;
use LBHurtado\XProvisioning\Models\ProvisioningRevision;

final readonly class XChangeProvisioningAuthorityProjector implements ProvisioningActivatorContract, ProvisioningRevokerContract
{
    public function __construct(
        private SystemUserResolverContract $systemUsers,
        private CommercialGovernanceJournal $commercialJournal,
        private PartnerApiGovernanceJournal $partnerApiJournal,
        private ActivateCommercialRecipientDesignation $activateRecipientDesignation,
        private RevokeCommercialRecipientDesignation $revokeRecipientDesignation,
        private TransitionCommercialRecipientDesignationEconomics $transitionRecipientEconomics,
        private WalletProvisioningContract $accounts,
        private TreasuryPrincipalReferenceResolverContract $principalReferences,
    ) {}

    public function activate(
        ProvisioningRevision $revision,
        ProvisioningAcceptance $acceptance,
        ?Model $checker = null,
    ): string {
        return DB::transaction(function () use ($revision, $acceptance, $checker): string {
            $revision->loadMissing('request');
            $candidate = $this->candidate($acceptance);

            if (! $checker instanceof Model) {
                throw new DomainException('Provisioning activation requires its recorded activation checker.');
            }

            if ($revision->request->profile === ProvisioningProfile::CommercialRecipientDesignation) {
                $reference = 'commercial-designation:'.$revision->request->reference.':'.$revision->snapshot_hash;
                $acceptedDesignation = CommercialRecipientDesignationData::fromArray((array) $revision->snapshot);
                $designation = $this->resolveRecipientDesignation(
                    $acceptedDesignation,
                    $candidate,
                );
                $this->activateRecipientDesignation->execute(
                    designation: $designation,
                    origin: 'provisioning_offer',
                    authorityReference: $reference,
                    sourceReference: (string) $revision->request->reference,
                    acceptedSnapshotHash: (string) $revision->snapshot_hash,
                    acceptanceEvidenceHash: (string) $acceptance->evidence_hash,
                    representativeType: $candidate->getMorphClass(),
                    representativeReference: (string) $candidate->getKey(),
                    activatedBy: $checker,
                    activatedAt: CarbonImmutable::now(),
                );
                if ($acceptedDesignation->supersedesDesignationReference !== null) {
                    $this->transitionRecipientEconomics->execute(
                        designation: $designation,
                        predecessorDesignationReference: $acceptedDesignation->supersedesDesignationReference,
                        checker: $checker,
                        authorizationReference: $reference,
                    );
                }

                return $reference;
            }

            $capabilities = $this->approvedCapabilities($revision);
            $this->assertSeparation($candidate, $capabilities);
            $reference = 'provisioning:'.$revision->request->reference.':'.$revision->snapshot_hash;

            foreach ($capabilities as $capability) {
                [$modelClass] = $this->authorizationTarget($capability);
                $authorization = $modelClass::query()->firstOrCreate([
                    'operator_type' => $candidate->getMorphClass(),
                    'operator_id' => $candidate->getKey(),
                    'capability' => $capability,
                    'authorization_reference' => $reference.':'.$capability,
                ], [
                    'granted_by_type' => $checker->getMorphClass(),
                    'granted_by_id' => $checker->getKey(),
                    'valid_from' => now(),
                ]);

                if ($authorization instanceof CommercialOperatorAuthorization && $authorization->wasRecentlyCreated) {
                    $this->commercialJournal->recordAuthorization($authorization);
                }

                if ($authorization instanceof PartnerApiOperatorAuthorization && $authorization->wasRecentlyCreated) {
                    $this->partnerApiJournal->recordAuthorization($authorization);
                }
            }

            return $reference;
        }, attempts: 3);
    }

    private function resolveRecipientDesignation(
        CommercialRecipientDesignationData $designation,
        Model $candidate,
    ): CommercialRecipientDesignationData {
        if ($designation->settlementAccountBinding !== CommercialSettlementAccountBinding::AcceptedCandidateAccount) {
            return $designation;
        }

        $account = $this->accounts->open($candidate, [
            'wallet' => [
                'slug' => (string) config('x-change.onboarding.default_wallet_slug', 'platform'),
                'name' => (string) config('x-change.onboarding.default_wallet_name', 'Platform Wallet'),
            ],
        ]);
        $accountReference = trim((string) data_get($account, 'uuid'));

        if ($accountReference === '') {
            throw new DomainException('The accepted commercial recipient has no stable Account reference.');
        }

        return new CommercialRecipientDesignationData(
            counterpartyReference: $designation->counterpartyReference,
            commercialRole: $designation->commercialRole,
            componentScope: $designation->componentScope,
            agreementReference: $designation->agreementReference,
            settlementDesignationReference: $designation->settlementDesignationReference,
            taxProfileReference: $designation->taxProfileReference,
            effectiveFrom: $designation->effectiveFrom,
            effectiveUntil: $designation->effectiveUntil,
            settlementDisposition: $designation->settlementDisposition,
            settlementAccountReference: 'wallet:'.$accountReference,
            settlementPrincipalReference: $this->principalReferences->resolve($candidate),
            settlementAccountBinding: CommercialSettlementAccountBinding::ExactAccount,
        );
    }

    public function revoke(
        ProvisioningRevision $revision,
        ProvisioningAcceptance $acceptance,
        string $reason,
    ): string {
        return DB::transaction(function () use ($revision, $acceptance, $reason): string {
            $revision->loadMissing('request');
            $candidate = $this->candidate($acceptance);
            $reference = 'provisioning:'.$revision->request->reference.':'.$revision->snapshot_hash;

            if ($revision->request->profile === ProvisioningProfile::CommercialRecipientDesignation) {
                $reference = 'commercial-designation:'.$revision->request->reference.':'.$revision->snapshot_hash;
                $this->revokeRecipientDesignation->execute(
                    authorityReference: $reference,
                    revocationReference: $reference.':revoked:'.hash('sha256', trim($reason)),
                );

                return $reference.':revoked';
            }

            foreach ($this->approvedCapabilities($revision) as $capability) {
                [$modelClass] = $this->authorizationTarget($capability);
                $modelClass::query()
                    ->where('operator_type', $candidate->getMorphClass())
                    ->where('operator_id', $candidate->getKey())
                    ->where('capability', $capability)
                    ->where('authorization_reference', $reference.':'.$capability)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now(), 'updated_at' => now()]);
            }

            return $reference.':revoked';
        }, attempts: 3);
    }

    private function candidate(ProvisioningAcceptance $acceptance): Model
    {
        $modelClass = (string) config('auth.providers.users.model');

        if (! is_subclass_of($modelClass, Model::class)) {
            throw new DomainException('The configured authentication model cannot receive provisioning authority.');
        }

        /** @var Model|null $candidate */
        $candidate = $modelClass::query()->find($acceptance->candidate_reference);

        if (! $candidate instanceof Model || $candidate->getMorphClass() !== $acceptance->candidate_type) {
            throw new DomainException('The verified provisioning candidate could not be resolved.');
        }

        $system = $this->systemUsers->resolve();

        if ($system instanceof Model && $candidate->is($system)) {
            throw new DomainException('The non-interactive System Principal cannot receive interactive authority.');
        }

        return $candidate;
    }

    /** @return list<string> */
    private function approvedCapabilities(ProvisioningRevision $revision): array
    {
        if ((string) data_get($revision->snapshot, 'activation_gate', 'operator_authority') !== 'operator_authority') {
            throw new DomainException('This profile requires its dedicated activation ceremony.');
        }

        $capabilities = array_values(array_unique(array_map(
            'strval',
            (array) data_get($revision->snapshot, 'capabilities', []),
        )));
        $allowed = array_values((array) config(
            "x-change.provisioning.operator_profiles.{$revision->request->profile->value}.capabilities",
            [],
        ));

        if ($capabilities === [] || array_diff($capabilities, $allowed) !== []) {
            throw new DomainException('The immutable provisioning capability snapshot is invalid.');
        }

        foreach ($capabilities as $capability) {
            $this->authorizationTarget($capability);
        }

        return $capabilities;
    }

    /** @return array{class-string<Model>, BackedEnum} */
    private function authorizationTarget(string $capability): array
    {
        foreach ([
            CommercialOperatorCapability::class => CommercialOperatorAuthorization::class,
            TreasuryOperatorCapability::class => TreasuryOperatorAuthorization::class,
            ProvisioningOperatorCapability::class => ProvisioningOperatorAuthorization::class,
            PartnerApiOperatorCapability::class => PartnerApiOperatorAuthorization::class,
        ] as $enumClass => $modelClass) {
            $enum = $enumClass::tryFrom($capability);

            if ($enum instanceof BackedEnum) {
                return [$modelClass, $enum];
            }
        }

        throw new DomainException("Unsupported provisioning capability [{$capability}].");
    }

    /** @param list<string> $capabilities */
    private function assertSeparation(Model $candidate, array $capabilities): void
    {
        $groups = [
            [
                [CommercialOperatorCapability::ManageOfferings->value, CommercialOperatorCapability::ManagePartners->value, CommercialOperatorCapability::RequestCommissionPayouts->value],
                [CommercialOperatorCapability::ApproveOfferings->value, CommercialOperatorCapability::ApprovePartners->value, CommercialOperatorCapability::ApproveCommissionPayouts->value, CommercialOperatorCapability::ExecuteCommissionPayouts->value],
                CommercialOperatorAuthorization::class,
            ],
            [
                [TreasuryOperatorCapability::RequestAccountGrants->value, TreasuryOperatorCapability::RequestInstitutionFunds->value, TreasuryOperatorCapability::RequestReconciliation->value],
                [TreasuryOperatorCapability::ApproveAccountGrants->value, TreasuryOperatorCapability::ExecuteAccountGrants->value, TreasuryOperatorCapability::ApproveInstitutionFunds->value, TreasuryOperatorCapability::ExecuteInstitutionFunds->value, TreasuryOperatorCapability::ApproveReconciliation->value, TreasuryOperatorCapability::ExecuteReconciliation->value],
                TreasuryOperatorAuthorization::class,
            ],
            [
                [ProvisioningOperatorCapability::Request->value, ProvisioningOperatorCapability::Issue->value],
                [ProvisioningOperatorCapability::Approve->value, ProvisioningOperatorCapability::Activate->value, ProvisioningOperatorCapability::Revoke->value],
                ProvisioningOperatorAuthorization::class,
            ],
            [
                [PartnerApiOperatorCapability::RequestProductionClients->value],
                [PartnerApiOperatorCapability::ApproveProductionClients->value, PartnerApiOperatorCapability::ActivateProductionClients->value],
                PartnerApiOperatorAuthorization::class,
            ],
        ];

        foreach ($groups as [$maker, $checker, $modelClass]) {
            $selectedMaker = array_intersect($capabilities, $maker) !== [];
            $selectedChecker = array_intersect($capabilities, $checker) !== [];

            if ($selectedMaker && $selectedChecker) {
                throw new DomainException('Maker and checker authority must belong to different operators.');
            }

            $opposite = $selectedMaker ? $checker : ($selectedChecker ? $maker : []);

            if ($opposite !== [] && $modelClass::query()
                ->where('operator_type', $candidate->getMorphClass())
                ->where('operator_id', $candidate->getKey())
                ->whereIn('capability', $opposite)
                ->currentlyValid()
                ->exists()) {
                throw new DomainException('The candidate already holds opposite maker-checker authority.');
            }
        }
    }
}
