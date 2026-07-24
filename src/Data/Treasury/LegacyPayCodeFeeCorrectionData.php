<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

final readonly class LegacyPayCodeFeeCorrectionData
{
    public function __construct(
        public string $status,
        public string $runReference,
        public int $voucherId,
        public string $connectionReference,
        public string $provider,
        public string $currency,
        public int $beneficiaryAmountMinor,
        public int $excessFeeAmountMinor,
        public ?string $inventoryCorrectionReference = null,
        public ?string $positionRecognitionReference = null,
        public ?string $positionAllocationReference = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'run_reference' => $this->runReference,
            'voucher_id' => $this->voucherId,
            'connection_reference' => $this->connectionReference,
            'provider' => $this->provider,
            'currency' => $this->currency,
            'beneficiary_amount_minor' => $this->beneficiaryAmountMinor,
            'excess_fee_amount_minor' => $this->excessFeeAmountMinor,
            'inventory_correction_reference' => $this->inventoryCorrectionReference,
            'position_recognition_reference' => $this->positionRecognitionReference,
            'position_allocation_reference' => $this->positionAllocationReference,
        ];
    }
}
