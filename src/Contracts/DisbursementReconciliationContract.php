<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;

interface DisbursementReconciliationContract
{
    public function reconcile(DisbursementReconciliationData $reconciliation): DisbursementReconciliationData;
}
