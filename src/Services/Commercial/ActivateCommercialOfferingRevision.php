<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Models\CommercialComponentEconomics;
use LBHurtado\XChange\Models\CommercialComponentEconomicsHead;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XCommerce\Data\CommercialCatalogItemData;
use LBHurtado\XCommerce\Data\CommercialComponentAllocationRuleData;
use LBHurtado\XCommerce\Data\CommercialComponentAllocationScheduleData;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsData;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsSetData;

final readonly class ActivateCommercialOfferingRevision
{
    public function __construct(
        private ActivateCommercialOffering $offerings,
        private CommercialComponentEconomicsManifestCompiler $manifests,
        private PersistCommercialComponentEconomicsManifest $persist,
        private ActivateCommercialComponentEconomics $economics,
    ) {}

    public function execute(
        CommercialOffering $offering,
        Model $actor,
        string $activationReference,
    ): void {
        DB::transaction(function () use ($offering, $actor, $activationReference): void {
            $lockedOffering = CommercialOffering::query()
                ->lockForUpdate()
                ->findOrFail($offering->getKey());
            $head = CommercialComponentEconomicsHead::query()
                ->with('currentActivation.economics.offering')
                ->whereKey($lockedOffering->profile)
                ->lockForUpdate()
                ->firstOrFail();
            $current = $head->currentActivation?->economics;

            if (! $current instanceof CommercialComponentEconomics) {
                throw new \DomainException('Commercial Offering activation requires active Agreement Economics.');
            }

            if ($current->commercial_offering_id === $lockedOffering->getKey()) {
                $this->offerings->execute(
                    offering: $lockedOffering,
                    authority: CommercialActivationAuthority::IndependentApproval,
                    activationReference: $activationReference,
                );

                return;
            }

            $revised = $this->reprice($current, $lockedOffering);
            $manifest = $this->manifests->compile(
                $lockedOffering->profile,
                $lockedOffering->offering(),
                (string) $lockedOffering->manifest_hash,
                $revised,
            );

            $this->offerings->execute(
                offering: $lockedOffering,
                authority: CommercialActivationAuthority::IndependentApproval,
                activationReference: $activationReference,
            );
            $persisted = $this->persist->execute(
                offering: $lockedOffering,
                manifest: $manifest,
                reference: $current->reference,
                version: $current->version + 1,
                origin: CommercialOfferingOrigin::MakerCheckerRevision,
                authority: CommercialActivationAuthority::IndependentApproval,
            );
            $this->economics->execute(
                economics: $persisted,
                authority: CommercialActivationAuthority::IndependentApproval,
                activationReference: 'component-economics-offering:'.$lockedOffering->profile.':'.$manifest->hash,
                actor: $actor,
                authorizationReference: $lockedOffering->authorization_reference,
            );
        }, attempts: 5);
    }

    private function reprice(
        CommercialComponentEconomics $current,
        CommercialOffering $offering,
    ): CommercialComponentEconomicsSetData {
        $currentSet = $current->economics();
        $components = collect($currentSet->components)->keyBy('componentReference');
        $oldItems = collect($current->offering->offering()->catalog->items)->keyBy('reference');

        $revised = array_map(function (CommercialCatalogItemData $item) use ($components, $oldItems): CommercialComponentEconomicsData {
            $component = $components->get($item->reference);
            $oldItem = $oldItems->get($item->reference);

            if (! $component instanceof CommercialComponentEconomicsData
                || ! $oldItem instanceof CommercialCatalogItemData) {
                throw new \DomainException("Agreement Economics does not cover catalog item [{$item->reference}].");
            }

            if ($item->unitPriceMinor === 0) {
                return new CommercialComponentEconomicsData(
                    componentReference: $item->reference,
                    billingUnit: null,
                    billableEventReference: null,
                    recognitionPolicyReference: null,
                    capabilityReferences: [],
                    allocationSchedule: null,
                    nonBillableReason: 'Explicitly non-billable because the canonical catalog price is zero.',
                );
            }

            if ($item->unitPriceMinor === $oldItem->unitPriceMinor) {
                return $component;
            }

            return $this->repriceBillableComponent($component, $item->unitPriceMinor);
        }, $offering->offering()->catalog->items);

        $economics = new CommercialComponentEconomicsSetData(
            reference: $currentSet->reference,
            version: $currentSet->version + 1,
            catalogReference: $offering->offering()->catalog->reference,
            catalogVersion: $offering->offering()->catalog->version,
            currency: $offering->offering()->catalog->currency,
            components: $revised,
        );
        $economics->assertMatchesCatalog($offering->offering()->catalog);

        return $economics;
    }

    private function repriceBillableComponent(
        CommercialComponentEconomicsData $component,
        int $unitPriceMinor,
    ): CommercialComponentEconomicsData {
        $schedule = $component->allocationSchedule;

        if (! $schedule instanceof CommercialComponentAllocationScheduleData
            || count($schedule->rules) !== 1
            || $schedule->rules[0]->fixedAmountMinor === null) {
            throw new \DomainException(
                "Catalog price changes for [{$component->componentReference}] require separately authored Agreement Economics.",
            );
        }

        $rule = $schedule->rules[0];

        return new CommercialComponentEconomicsData(
            componentReference: $component->componentReference,
            billingUnit: $component->billingUnit,
            billableEventReference: $component->billableEventReference,
            recognitionPolicyReference: $component->recognitionPolicyReference,
            capabilityReferences: $component->capabilityReferences,
            allocationSchedule: new CommercialComponentAllocationScheduleData(
                reference: $schedule->reference,
                version: $schedule->version + 1,
                currency: $schedule->currency,
                rules: [new CommercialComponentAllocationRuleData(
                    reference: $rule->reference,
                    sequence: $rule->sequence,
                    lineType: $rule->lineType,
                    category: $rule->category,
                    destinationKind: $rule->destinationKind,
                    recipientReference: $rule->recipientReference,
                    participantRole: $rule->participantRole,
                    fixedAmountMinor: $unitPriceMinor,
                    basisPoints: null,
                    agreementReference: $rule->agreementReference,
                    designationReference: $rule->designationReference,
                    taxPolicyReference: $rule->taxPolicyReference,
                )],
            ),
        );
    }
}
