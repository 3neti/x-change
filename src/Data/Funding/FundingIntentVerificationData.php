<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use Spatie\LaravelData\Data;

class FundingIntentVerificationData extends Data
{
    public function __construct(
        public FundingVerificationTrigger $trigger,
        public string $actorId,
        public ?int $webhookReceiptId = null,
    ) {}
}
