<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;

interface DisbursementStatusFetcherContract
{
    /**
     * Fetch the latest provider-side status for a previously recorded payout.
     *
     * @return array<string, mixed>
     */
    public function fetch(DisbursementReconciliationData $reconciliation): array;
}
