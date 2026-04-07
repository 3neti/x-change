<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Onboarding;

use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\WalletData;
use Spatie\LaravelData\Data;

class OpenIssuerWalletResultData extends Data
{
    public function __construct(
        public IssuerData $issuer,
        public WalletData $wallet,
    ) {}
}
