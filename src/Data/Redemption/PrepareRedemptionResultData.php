<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Redemption;

use Spatie\LaravelData\Data;

class PrepareRedemptionResultData extends Data
{
    /**
     * @param  array<int, string>  $messages
     */
    public function __construct(
        public string $voucher_code,
        public bool $can_start,
        public string $entry_route,
        public VoucherRedemptionProfileData $profile,
        public RedemptionRequirementsData $requirements,
        public RedemptionFlowData $flow,
        public array $messages = [],
    ) {}
}
