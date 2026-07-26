<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use LBHurtado\Voucher\Models\Voucher;

final readonly class ApproveFundingRequestResult
{
    public function __construct(
        public Voucher $voucher,
        public bool $newlyApproved,
    ) {}
}
