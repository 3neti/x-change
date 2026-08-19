<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;
use LBHurtado\XChange\Contracts\Execution\StoredValueDestinationAuthorityContract;
use LBHurtado\XChange\Data\Execution\StoredValueDestinationAuthorityData;

final class UnavailableStoredValueDestinationAuthority implements StoredValueDestinationAuthorityContract
{
    public function isReady(): bool
    {
        return false;
    }

    public function authorize(
        ExecutionContextData $context,
        int $amountMinor,
    ): StoredValueDestinationAuthorityData {
        throw new StoredValueSpendRejectedException(
            'Stored value destination authority has not been commissioned.',
        );
    }
}
