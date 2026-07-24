<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

final readonly class HistoricalStandingFundingBackfillData
{
    /**
     * @param  list<string>  $receiptReferences
     */
    public function __construct(
        public string $status,
        public string $connectionReference,
        public string $provider,
        public string $currency,
        public int $candidateCount,
        public int $backfilledCount,
        public int $amountMinor,
        public array $receiptReferences,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'connection_reference' => $this->connectionReference,
            'provider' => $this->provider,
            'currency' => $this->currency,
            'candidate_count' => $this->candidateCount,
            'backfilled_count' => $this->backfilledCount,
            'amount_minor' => $this->amountMinor,
            'receipt_references' => $this->receiptReferences,
        ];
    }
}
