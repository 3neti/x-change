<?php

namespace LBHurtado\XChange\Adapters;

use LBHurtado\Cash\Contracts\ClaimantIdentityContract;
use LBHurtado\Contact\Models\Contact;

class ContactClaimantIdentityAdapter implements ClaimantIdentityContract
{
    public function __construct(
        protected Contact $contact,
    ) {}

    public function getClaimantId(): string|int|null
    {
        return $this->contact->getKey();
    }

    public function getClaimantMobile(): ?string
    {
        return $this->contact->mobile ?? null;
    }
}
