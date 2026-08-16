<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Contracts\CommercialComponentEconomicsResolverContract;
use LBHurtado\XChange\Models\CommercialRecipientDesignation;
use LBHurtado\XCommerce\Enums\CommercialAllocationDestinationKind;
use LBHurtado\XProvisioning\Data\CommercialRecipientDesignationData;

final readonly class ProvisionCommercialRecipientDesignationBaselines
{
    public function __construct(
        private CommercialComponentEconomicsResolverContract $economics,
        private ActivateCommercialRecipientDesignation $activate,
    ) {}

    /** @return list<CommercialRecipientDesignation> */
    public function provision(string $commissioningManifestReference): array
    {
        $rules = [];

        foreach ((array) config('x-change.commercial.offerings.profiles', ['pay_code', 'account_funding']) as $profile) {
            foreach ($this->economics->resolve((string) $profile)->components as $component) {
                foreach ($component->allocationSchedule?->rules ?? [] as $rule) {
                    if ($rule->destinationKind !== CommercialAllocationDestinationKind::ExternalRecipient) {
                        continue;
                    }

                    $key = implode('|', [
                        $rule->designationReference,
                        $rule->recipientReference,
                        $rule->participantRole,
                        $rule->agreementReference,
                    ]);
                    $rules[$key]['rule'] = $rule;
                    $rules[$key]['components'][$component->componentReference] = $component->componentReference;
                }
            }
        }

        $designations = [];

        foreach ($rules as $entry) {
            $rule = $entry['rule'];
            $scope = array_values($entry['components']);
            sort($scope, SORT_STRING);
            $designation = new CommercialRecipientDesignationData(
                counterpartyReference: $rule->recipientReference,
                commercialRole: $rule->participantRole,
                componentScope: $scope,
                agreementReference: (string) $rule->agreementReference,
                settlementDesignationReference: (string) $rule->designationReference,
                taxProfileReference: null,
                effectiveFrom: '1970-01-01T00:00:00+00:00',
            );
            $snapshotHash = hash('sha256', json_encode($designation->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $designations[] = $this->activate->execute(
                designation: $designation,
                origin: 'commissioning_manifest',
                authorityReference: 'commercial-recipient-designation-baseline:'.$rule->designationReference.':'.$snapshotHash,
                sourceReference: $commissioningManifestReference,
                acceptedSnapshotHash: $snapshotHash,
                activatedAt: CarbonImmutable::parse('1970-01-01T00:00:00+00:00'),
            );
        }

        return $designations;
    }
}
