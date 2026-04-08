<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\RedemptionCompletionStoreContract;

class NullRedemptionCompletionStore implements RedemptionCompletionStoreContract
{
    public function findByReference(string $referenceId): ?array
    {
        return null;
    }

    public function findByFlowId(string $flowId): ?array
    {
        return null;
    }
}
