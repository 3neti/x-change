<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use Spatie\LaravelData\Data;

class WithdrawalDisbursementExecutionData extends Data
{
    public function __construct(
        public PayoutRequestData $input,
        public PayoutResultData $response,
        public string $status,
        public ?string $message = null,
    ) {}
}
