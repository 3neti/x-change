<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claims;

use Spatie\LaravelData\Data;

class ClaimApprovalInitiationResultData extends Data
{
    public function __construct(
        public string $voucher_code,
        public string $status,
        public array $requirements = [],
        public array $actions = [],
        public array $meta = [],
        public array $messages = [],
    ) {}
}
