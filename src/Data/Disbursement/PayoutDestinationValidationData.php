<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Disbursement;

use Spatie\LaravelData\Data;

final class PayoutDestinationValidationData extends Data
{
    /**
     * @param  array<string, mixed>  $checks
     */
    public function __construct(
        public string $status,
        public string $bankCode,
        public string $accountNumber,
        public ?string $mobile,
        public string $message,
        public bool $providerVerified,
        public array $checks = [],
    ) {}

    public function isValid(): bool
    {
        return $this->status !== 'invalid';
    }
}
