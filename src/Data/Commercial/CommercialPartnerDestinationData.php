<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Commercial;

use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class CommercialPartnerDestinationData extends Data
{
    public function __construct(
        public readonly string $provider,
        public readonly string $connectionReference,
        public readonly string $currency,
        public readonly string $bankCode,
        public readonly string $accountNumber,
        public readonly string $recipientName,
        public readonly string $mobile,
        public readonly string $authorizationReference,
    ) {
        foreach ([
            'provider' => $this->provider,
            'connection reference' => $this->connectionReference,
            'currency' => $this->currency,
            'bank code' => $this->bankCode,
            'account number' => $this->accountNumber,
            'recipient name' => $this->recipientName,
            'mobile' => $this->mobile,
            'authorization reference' => $this->authorizationReference,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Commercial Partner destination {$field} is required.");
            }
        }

        if (preg_match('/^[A-Z]{3}$/', mb_strtoupper($this->currency)) !== 1) {
            throw new InvalidArgumentException('Commercial Partner destination currency is invalid.');
        }
    }
}
