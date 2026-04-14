<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;
use LBHurtado\XChange\Models\DisbursementReconciliation;

interface DisbursementReconciliationContract
{
    /**
     * @return array<string, mixed>
     */
    public function reconcile(DisbursementReconciliation|DisbursementReconciliationData $reconciliation): array;
}
