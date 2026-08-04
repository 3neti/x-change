<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Disbursement;

use LBHurtado\XChange\Contracts\ProviderPayoutDestinationValidationContract;
use LBHurtado\XChange\Data\Disbursement\PayoutDestinationValidationData;

final readonly class UnavailableProviderPayoutDestinationValidation implements ProviderPayoutDestinationValidationContract
{
    public function validate(
        string $bankCode,
        string $accountNumber,
        string $settlementRail,
        ?string $mobile = null,
    ): ?PayoutDestinationValidationData {
        return null;
    }
}
