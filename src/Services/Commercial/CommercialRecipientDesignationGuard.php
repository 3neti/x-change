<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use DomainException;
use LBHurtado\XChange\Contracts\CommercialRecipientDesignationResolverContract;
use LBHurtado\XCommerce\Data\CommercialAllocationPlanData;
use LBHurtado\XCommerce\Enums\CommercialAllocationDestinationKind;

final readonly class CommercialRecipientDesignationGuard
{
    public function __construct(private CommercialRecipientDesignationResolverContract $designations) {}

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
        }
    }
}
