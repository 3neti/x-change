<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Reconciliation;

use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;
use RuntimeException;

class ReconcileDisbursement
{
    public function __construct(
        protected DisbursementReconciliationStoreContract $store,
        protected DisbursementReconciliationContract $service,
    ) {}

    public function handle(int $reconciliationId): DisbursementReconciliationData
    {
        $record = $this->store->findById($reconciliationId);

        if (! $record) {
            throw new RuntimeException("Disbursement reconciliation [{$reconciliationId}] not found.");
        }

        return DisbursementReconciliationData::from(
            $this->service->reconcile($record)
        );
    }
}
