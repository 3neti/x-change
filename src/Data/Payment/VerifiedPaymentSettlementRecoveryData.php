<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Payment;

final readonly class VerifiedPaymentSettlementRecoveryData
{
    /**
     * @param  array<string, string>  $operationReferences
     */
    public function __construct(
        public string $status,
        public string $attemptReference,
        public string $voucherReference,
        public string $provider,
        public int $amountMinor,
        public string $currency,
        public int $observationId,
        public string $providerTransactionHash,
        public array $operationReferences,
        public bool $committed,
        public bool $providerCalls = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'attempt_reference' => $this->attemptReference,
            'voucher_reference' => $this->voucherReference,
            'provider' => $this->provider,
            'amount_minor' => $this->amountMinor,
            'currency' => $this->currency,
            'provider_observation_id' => $this->observationId,
            'provider_transaction_hash' => $this->providerTransactionHash,
            'operation_references' => $this->operationReferences,
            'committed' => $this->committed,
            'provider_calls' => $this->providerCalls,
        ];
    }
}
