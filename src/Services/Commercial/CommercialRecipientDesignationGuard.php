<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use DomainException;
use LBHurtado\XChange\Contracts\CommercialRecipientDesignationResolverContract;
use LBHurtado\XCommerce\Data\CommercialAllocationPlanData;
use LBHurtado\XCommerce\Enums\CommercialAllocationDestinationKind;

final readonly class CommercialRecipientDesignationGuard
{
    public function __construct(
        private CommercialRecipientDesignationResolverContract $designations,
        private CommercialTaxProfileRegistry $taxProfiles,
    ) {}

    public function assertPlan(CommercialAllocationPlanData $plan): void
    {
        foreach ($plan->lines as $line) {
            if ($line->destinationKind !== CommercialAllocationDestinationKind::ExternalRecipient) {
                continue;
            }

            $designation = $this->designations->resolve((string) $line->designationReference);

            if ($designation->counterparty_reference !== $line->recipientReference
                || $designation->commercial_role !== $line->participantRole
                || $designation->agreement_reference !== $line->agreementReference
                || ! in_array($line->componentReference, (array) $designation->component_scope, true)) {
                throw new DomainException(
                    "Commercial Recipient Designation [{$line->designationReference}] does not authorize component [{$line->componentReference}].",
                );
            }

            $ruleTaxProfile = filled($line->taxPolicyReference)
                ? (string) $line->taxPolicyReference
                : null;
            $designationTaxProfile = filled($designation->tax_profile_reference)
                ? (string) $designation->tax_profile_reference
                : null;

            if ($ruleTaxProfile !== $designationTaxProfile) {
                throw new DomainException(
                    "Commercial Recipient Designation [{$line->designationReference}] does not authorize tax profile [{$ruleTaxProfile}].",
                );
            }

            if ($ruleTaxProfile !== null) {
                $profile = $this->taxProfiles->resolve($ruleTaxProfile);

                if ($profile->currency !== $line->currency) {
                    throw new DomainException(
                        "Commercial Tax Profile [{$ruleTaxProfile}] does not authorize currency [{$line->currency}].",
                    );
                }
            }
        }
    }
}
