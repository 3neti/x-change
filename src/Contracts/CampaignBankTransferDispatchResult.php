<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

final readonly class CampaignBankTransferDispatchResult
{
    public function __construct(
        public string $status,
        public ?string $providerTransferReference = null,
        public ?string $reason = null,
    ) {}
}
