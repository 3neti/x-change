<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Payment;

final readonly class CampaignPaymentRecognitionData
{
    public function __construct(
        public string $recognitionReference,
        public string $campaignReference,
        public string $campaignRevisionId,
        public string $bindingReference,
        public string $provider,
        public string $providerTransactionKey,
        public int $grossAmountMinor,
        public int $netAmountMinor,
        public string $currency,
        public ?string $settlementRail,
        public int $evidenceCount,
        public string $occurredAt,
        public string $settledAt,
        public string $recognizedAt,
    ) {}

    /** @return array<string, int|string|null> */
    public function toBroadcastArray(): array
    {
        return [
            'schema' => 'x-change.campaign-payment-recognized.v1',
            'recognition_reference' => $this->recognitionReference,
            'campaign_reference' => $this->campaignReference,
            'campaign_revision_id' => $this->campaignRevisionId,
            'binding_reference' => $this->bindingReference,
            'provider' => $this->provider,
            'gross_amount_minor' => $this->grossAmountMinor,
            'net_amount_minor' => $this->netAmountMinor,
            'currency' => $this->currency,
            'settlement_rail' => $this->settlementRail,
            'evidence_count' => $this->evidenceCount,
            'occurred_at' => $this->occurredAt,
            'settled_at' => $this->settledAt,
            'recognized_at' => $this->recognizedAt,
        ];
    }
}
