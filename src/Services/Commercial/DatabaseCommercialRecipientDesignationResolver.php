<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use DomainException;
use LBHurtado\XChange\Contracts\CommercialRecipientDesignationResolverContract;
use LBHurtado\XChange\Models\CommercialRecipientDesignation;

final readonly class DatabaseCommercialRecipientDesignationResolver implements CommercialRecipientDesignationResolverContract
{
    public function __construct(
        private CommercialRecipientDesignationAuthorityVerifier $authority,
    ) {}

    public function resolve(string $designationReference): CommercialRecipientDesignation
    {
        $designation = CommercialRecipientDesignation::query()
            ->where('designation_reference', $designationReference)
            ->currentlyEffective()
            ->first();

        if (! $designation instanceof CommercialRecipientDesignation) {
            throw new DomainException("Commercial Recipient Designation [{$designationReference}] is not active.");
        }

        $this->authority->assertValid($designation);

        return $designation;
    }
}
