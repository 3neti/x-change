<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Models\CommercialRecipientDesignation;

interface CommercialRecipientDesignationResolverContract
{
    public function resolve(string $designationReference): CommercialRecipientDesignation;
}
