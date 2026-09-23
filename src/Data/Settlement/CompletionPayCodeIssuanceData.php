<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\CompletionPayCodeIssuance;

final readonly class CompletionPayCodeIssuanceData
{
    public function __construct(
        public CompletionPayCodeIssuance $issuance,
        public Voucher $voucher,
        public bool $created,
    ) {}
}
