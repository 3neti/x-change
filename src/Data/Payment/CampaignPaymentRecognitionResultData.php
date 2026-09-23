<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Payment;

use LBHurtado\XChange\Models\CampaignPaymentEvidenceQuarantine;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;

final readonly class CampaignPaymentRecognitionResultData
{
    public function __construct(
        public ?CampaignPaymentRecognition $recognition = null,
        public ?CampaignPaymentEvidenceQuarantine $quarantine = null,
    ) {}

    public function recognized(): bool
    {
        return $this->recognition instanceof CampaignPaymentRecognition;
    }

    public function quarantined(): bool
    {
        return $this->quarantine instanceof CampaignPaymentEvidenceQuarantine;
    }
}
