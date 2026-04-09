<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\XChange\Contracts\DisbursementStatusFetcherContract;
use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;
use RuntimeException;

class DefaultDisbursementStatusFetcherService implements DisbursementStatusFetcherContract
{
    public function __construct(
        protected PayoutProvider $provider,
    ) {}

    public function fetch(DisbursementReconciliationData $reconciliation): array
    {
        if (! method_exists($this->provider, 'status')) {
            throw new RuntimeException('Configured payout provider does not support status fetching.');
        }

        $result = $this->provider->status(
            reference: $reconciliation->provider_reference,
            transactionId: $reconciliation->provider_transaction_id,
        );

        if (is_array($result)) {
            return $result;
        }

        if (is_object($result) && method_exists($result, 'toArray')) {
            return $result->toArray();
        }

        return (array) $result;
    }
}
