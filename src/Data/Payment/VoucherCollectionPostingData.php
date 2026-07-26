<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Payment;

final readonly class VoucherCollectionPostingData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?int $walletTransactionId = null,
        public ?string $treasuryOperationReference = null,
        public array $metadata = [],
    ) {}
}
