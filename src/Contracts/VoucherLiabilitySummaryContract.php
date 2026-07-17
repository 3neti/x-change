<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Money\VoucherLiabilitySummaryData;

interface VoucherLiabilitySummaryContract
{
    public function forIssuer(mixed $issuer): VoucherLiabilitySummaryData;
}
