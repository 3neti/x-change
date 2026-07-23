<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use Spatie\LaravelData\Data;

final class NetbankReusableFundingAddressData extends Data
{
    public function __construct(
        public string $provider,
        public string $fundingAddress,
        public string $maskedFundingAddress,
        public string $currency,
        public string $institution,
        public string $merchantName,
        public string $qrCode,
        public string $qrMode,
        public string $transactionType,
        public bool $embeddedAmount,
        public bool $providerGenerated,
        public bool $temporary,
        public bool $fundingIntentCreated,
        public bool $automaticCreditEnabled,
    ) {}
}
