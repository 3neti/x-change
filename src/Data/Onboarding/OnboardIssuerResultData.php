<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Onboarding;

use LBHurtado\XChange\Data\IssuerData;
use Spatie\LaravelData\Data;

class OnboardIssuerResultData extends Data
{
    public function __construct(
        public IssuerData $issuer,
    ) {}
}
