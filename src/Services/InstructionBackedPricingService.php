<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Contracts\PricingServiceContract;
use LBHurtado\XChange\Services\Commercial\PayCodeCommercialQuoteService;

final readonly class InstructionBackedPricingService implements PricingServiceContract
{
    public function __construct(
        private PayCodeCommercialQuoteService $quotes,
    ) {}

    public function estimate(VoucherInstructionsData $instructions): array
    {
        return $this->quotes->estimate($instructions);
    }
}
