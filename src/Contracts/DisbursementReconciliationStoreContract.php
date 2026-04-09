<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;

interface DisbursementReconciliationStoreContract
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes): DisbursementReconciliationData;

    public function findByVoucherReferenceAndClaim(
        string $voucherCode,
        ?string $providerReference,
        ?string $claimType,
    ): ?DisbursementReconciliationData;

    public function findById(int $id): ?DisbursementReconciliationData;

    /**
     * @return array<int, DisbursementReconciliationData>
     */
    public function getPending(int $limit = 50): array;
}
