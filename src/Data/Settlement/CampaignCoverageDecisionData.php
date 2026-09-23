<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use InvalidArgumentException;

final readonly class CampaignCoverageDecisionData
{
    private function __construct(
        public bool $eligible,
        public string $reasonCode,
        public ?ProvisionalCoverageTermsData $terms,
    ) {
        if ($this->eligible !== ($this->terms instanceof ProvisionalCoverageTermsData)) {
            throw new InvalidArgumentException(
                'Eligible campaign coverage decisions require terms; ineligible decisions cannot contain them.',
            );
        }

        if (trim($this->reasonCode) === '') {
            throw new InvalidArgumentException('Campaign coverage decisions require a reason code.');
        }
    }

    public static function eligible(
        ProvisionalCoverageTermsData $terms,
        string $reasonCode = 'eligible',
    ): self {
        return new self(true, $reasonCode, $terms);
    }

    public static function ineligible(string $reasonCode): self
    {
        return new self(false, $reasonCode, null);
    }
}
