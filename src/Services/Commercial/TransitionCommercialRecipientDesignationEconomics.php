<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialComponentEconomicsActivation;
use LBHurtado\XChange\Models\CommercialComponentEconomicsHead;
use LBHurtado\XCommerce\Data\CommercialComponentAllocationRuleData;
use LBHurtado\XCommerce\Data\CommercialComponentAllocationScheduleData;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsData;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsSetData;
use LBHurtado\XCommerce\Enums\CommercialAllocationDestinationKind;
use LBHurtado\XProvisioning\Data\CommercialRecipientDesignationData;

final readonly class TransitionCommercialRecipientDesignationEconomics
{
    public function __construct(
        private CommercialOperatorAuthorityContract $authority,
        private CommercialComponentEconomicsManifestCompiler $manifests,
        private PersistCommercialComponentEconomicsManifest $persist,
        private ActivateCommercialComponentEconomics $activate,
    ) {}

    /** @return list<CommercialComponentEconomicsActivation> */
    public function execute(
        CommercialRecipientDesignationData $designation,
        string $predecessorDesignationReference,
        Model $checker,
        string $authorizationReference,
    ): array {
        if (! $this->authority->allows($checker, CommercialOperatorCapability::ApproveOfferings)) {
            throw new AuthorizationException('Operator lacks [commercial.offerings.approve] authority.');
        }

        $predecessor = trim($predecessorDesignationReference);
        if ($predecessor === '') {
            throw new \DomainException('Commercial recipient economics transition requires an immutable predecessor designation.');
        }

        return DB::transaction(function () use ($designation, $predecessor, $checker, $authorizationReference): array {
            $profiles = array_values(array_map(
                'strval',
                (array) config('x-change.commercial.offerings.profiles', ['pay_code', 'account_funding']),
            ));
            $heads = CommercialComponentEconomicsHead::query()
                ->with('currentActivation.economics.offering')
                ->whereIn('profile', $profiles)
                ->lockForUpdate()
                ->get()
                ->keyBy('profile');
            $activations = [];
            $affectedComponents = [];

            foreach ($profiles as $profile) {
                $current = $heads->get($profile)?->currentActivation?->economics;
                if ($current === null) {
                    throw new \DomainException("Commercial Component Economics profile [{$profile}] is not active.");
                }

                [$economics, $profileComponents] = $this->replaceDesignation(
                    $current->economics(),
                    $designation,
                    $predecessor,
                );
                $affectedComponents = [...$affectedComponents, ...$profileComponents];

                if ($profileComponents === []) {
                    continue;
                }

                $manifest = $this->manifests->compile(
                    $profile,
                    $current->offering->offering(),
                    (string) $current->offering_manifest_hash,
                    $economics,
                );
                $persisted = $this->persist->execute(
                    offering: $current->offering,
                    manifest: $manifest,
                    reference: $current->reference,
                    version: $current->version + 1,
                    origin: CommercialOfferingOrigin::MakerCheckerRevision,
                    authority: CommercialActivationAuthority::IndependentApproval,
                );
                $activations[] = $this->activate->execute(
                    economics: $persisted,
                    authority: CommercialActivationAuthority::IndependentApproval,
                    activationReference: 'component-economics-designation:'.$profile.':'.$manifest->hash,
                    actor: $checker,
                    authorizationReference: $authorizationReference,
                );
            }

            $affectedComponents = array_values(array_unique($affectedComponents));
            sort($affectedComponents, SORT_STRING);
            $authorizedComponents = $designation->componentScope;
            sort($authorizedComponents, SORT_STRING);

            if ($activations === [] || $affectedComponents !== $authorizedComponents) {
                throw new \DomainException('Active Component Economics changed after the recipient authority snapshot was submitted.');
            }

            return $activations;
        }, attempts: 5);
    }

    /** @return array{CommercialComponentEconomicsSetData, list<string>} */
    private function replaceDesignation(
        CommercialComponentEconomicsSetData $current,
        CommercialRecipientDesignationData $designation,
        string $predecessor,
    ): array {
        $affected = [];
        $components = array_map(function (CommercialComponentEconomicsData $component) use (
            $designation,
            $predecessor,
            &$affected,
        ): CommercialComponentEconomicsData {
            if ($component->allocationSchedule === null) {
                return $component;
            }

            $replaced = false;
            $rules = array_map(function (CommercialComponentAllocationRuleData $rule) use (
                $designation,
                $predecessor,
                &$replaced,
            ): CommercialComponentAllocationRuleData {
                if ($rule->designationReference !== $predecessor) {
                    return $rule;
                }

                if ($rule->destinationKind !== CommercialAllocationDestinationKind::ExternalRecipient
                    || $rule->recipientReference !== $designation->counterpartyReference
                    || $rule->participantRole !== $designation->commercialRole
                    || $rule->agreementReference !== $designation->agreementReference
                    || $rule->taxPolicyReference !== $designation->taxProfileReference) {
                    throw new \DomainException('Active Component Economics no longer matches the accepted recipient authority.');
                }

                $replaced = true;

                return new CommercialComponentAllocationRuleData(
                    reference: $rule->reference,
                    sequence: $rule->sequence,
                    lineType: $rule->lineType,
                    category: $rule->category,
                    destinationKind: $rule->destinationKind,
                    recipientReference: $rule->recipientReference,
                    participantRole: $rule->participantRole,
                    fixedAmountMinor: $rule->fixedAmountMinor,
                    basisPoints: $rule->basisPoints,
                    agreementReference: $rule->agreementReference,
                    designationReference: $designation->settlementDesignationReference,
                    taxPolicyReference: $rule->taxPolicyReference,
                );
            }, $component->allocationSchedule->rules);

            if (! $replaced) {
                return $component;
            }

            $affected[] = $component->componentReference;

            return new CommercialComponentEconomicsData(
                componentReference: $component->componentReference,
                billingUnit: $component->billingUnit,
                billableEventReference: $component->billableEventReference,
                recognitionPolicyReference: $component->recognitionPolicyReference,
                capabilityReferences: $component->capabilityReferences,
                allocationSchedule: new CommercialComponentAllocationScheduleData(
                    reference: $component->allocationSchedule->reference,
                    version: $component->allocationSchedule->version + 1,
                    currency: $component->allocationSchedule->currency,
                    rules: $rules,
                ),
            );
        }, $current->components);

        return [new CommercialComponentEconomicsSetData(
            reference: $current->reference,
            version: $current->version + 1,
            catalogReference: $current->catalogReference,
            catalogVersion: $current->catalogVersion,
            currency: $current->currency,
            components: $components,
        ), array_values(array_unique($affected))];
    }
}
