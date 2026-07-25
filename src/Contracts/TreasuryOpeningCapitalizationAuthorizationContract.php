<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Treasury\TreasuryOpeningCapitalizationAuthorizationData;

interface TreasuryOpeningCapitalizationAuthorizationContract
{
    public function authorize(
        TreasuryOpeningCapitalizationAuthorizationData $request,
    ): void;
}
