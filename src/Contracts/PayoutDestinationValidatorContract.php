<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Disbursement\PayoutDestinationValidationData;

interface PayoutDestinationValidatorContract
{
    public function validate(
        string $bankCode,
        string $accountNumber,
        string $settlementRail,
        ?string $mobile = null,
    ): PayoutDestinationValidationData;
}
