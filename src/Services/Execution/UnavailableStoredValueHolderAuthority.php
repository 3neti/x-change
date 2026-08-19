<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;
use LBHurtado\XChange\Contracts\Execution\StoredValueHolderAuthorityContract;
use LBHurtado\XChange\Data\Execution\StoredValueHolderAuthorityData;

final class UnavailableStoredValueHolderAuthority implements StoredValueHolderAuthorityContract
{
    public function isReady(): bool
    {
        return false;
    }

    public function authorize(ExecutionContextData $context): StoredValueHolderAuthorityData
    {
        throw new StoredValueSpendRejectedException(
            'Stored value holder authority has not been commissioned.',
        );
    }
}
