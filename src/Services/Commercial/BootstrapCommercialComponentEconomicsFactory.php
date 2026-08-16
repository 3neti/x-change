<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XCommerce\Data\CommercialComponentAllocationRuleData;
use LBHurtado\XCommerce\Data\CommercialComponentAllocationScheduleData;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsData;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsSetData;
use LBHurtado\XCommerce\Data\CommercialOfferingData;
use LBHurtado\XCommerce\Enums\CommercialAllocationDestinationKind;
use LBHurtado\XCommerce\Enums\CommercialWaterfallLineType;

final readonly class BootstrapCommercialComponentEconomicsFactory
{
    public function make(string $profile, CommercialOfferingData $offering): CommercialComponentEconomicsSetData
    {
        $policy = (array) config('x-change.commercial.component_economics.bootstrap', []);
        $components = [];

        foreach ($offering->catalog->items as $item) {
            if ($item->deprecated || $item->unitPriceMinor === 0) {
                $components[] = new CommercialComponentEconomicsData(
                    componentReference: $item->reference,
                    billingUnit: null,
                    billableEventReference: null,
                    recognitionPolicyReference: null,
                    capabilityReferences: [],
                    allocationSchedule: null,
                    nonBillableReason: $item->deprecated
                        ? 'Explicitly non-billable because the catalog item is deprecated.'
                        : 'Explicitly non-billable because the canonical catalog price is zero.',
                );

                continue;
            }

            $components[] = new CommercialComponentEconomicsData(
                componentReference: $item->reference,
                billingUnit: (string) ($policy['billing_unit'] ?? 'selected_instruction'),
                billableEventReference: (string) ($policy['billable_event_reference'] ?? 'pay_code.issued_with_component'),
                recognitionPolicyReference: (string) ($policy['recognition_policy_reference'] ?? 'recognition:pay-code-issuance:v1'),
                capabilityReferences: array_values((array) (($policy['capabilities'] ?? [])[$item->reference] ?? [])),
                allocationSchedule: new CommercialComponentAllocationScheduleData(
                    reference: 'component-allocation:'.$profile.':'.$item->reference,
                    version: (int) ($policy['version'] ?? 1),
                    currency: $item->currency,
                    rules: [
                        new CommercialComponentAllocationRuleData(
                            reference: '3neti-default-share',
                            sequence: 10,
                            lineType: CommercialWaterfallLineType::Allocation,
                            category: (string) ($policy['category'] ?? 'service_provider_payable'),
                            destinationKind: CommercialAllocationDestinationKind::ExternalRecipient,
                            recipientReference: (string) ($policy['recipient_reference'] ?? 'counterparty:3neti'),
                            participantRole: (string) ($policy['participant_role'] ?? 'service_aggregator'),
                            fixedAmountMinor: $item->unitPriceMinor,
                            agreementReference: (string) ($policy['agreement_reference'] ?? 'agreement:commissioning:institution-3neti:v1'),
                            designationReference: (string) ($policy['designation_reference'] ?? 'designation:commissioning:3neti:v1'),
                            taxPolicyReference: filled($policy['tax_policy_reference'] ?? null)
                                ? (string) $policy['tax_policy_reference']
                                : null,
                        ),
                    ],
                ),
            );
        }

        $economics = new CommercialComponentEconomicsSetData(
            reference: 'component-economics:'.$profile,
            version: (int) ($policy['version'] ?? 1),
            catalogReference: $offering->catalog->reference,
            catalogVersion: $offering->catalog->version,
            currency: $offering->catalog->currency,
            components: $components,
        );
        $economics->assertMatchesCatalog($offering->catalog);

        return $economics;
    }
}
