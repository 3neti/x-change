<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Reconciliation;

use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordDisbursementReconciliation
{
    use AsAction;

    public function __construct(
        protected DisbursementReconciliationStoreContract $store,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): DisbursementReconciliationData
    {
        return $this->store->record($attributes);
    }
}
