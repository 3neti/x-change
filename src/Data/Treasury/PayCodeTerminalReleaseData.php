<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

final readonly class PayCodeTerminalReleaseData
{
    public function __construct(
        public string $status,
        public string $terminalReason,
        public ?string $operationReference,
        public int $amountMinor,
        public ?string $currency,
        public bool $replayed = false,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     terminal_reason: string,
     *     operation_reference: string|null,
     *     amount_minor: int,
     *     currency: string|null,
     *     replayed: bool,
     *     provider_calls: false,
     *     provider_inventory_changed: false,
     *     issuance_charges_refunded: false
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'terminal_reason' => $this->terminalReason,
            'operation_reference' => $this->operationReference,
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency,
            'replayed' => $this->replayed,
            'provider_calls' => false,
            'provider_inventory_changed' => false,
            'issuance_charges_refunded' => false,
        ];
    }
}
