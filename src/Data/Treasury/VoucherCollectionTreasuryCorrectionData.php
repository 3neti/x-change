<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

final readonly class VoucherCollectionTreasuryCorrectionData
{
    /**
     * @param  array<string, string>  $operationReferences
     */
    public function __construct(
        public string $status,
        public string $voucherCode,
        public int $collectionId,
        public string $provider,
        public string $currency,
        public int $amountMinor,
        public int $compatibilityBalanceMinor,
        public int $clientFundsBalanceMinor,
        public int $divergenceMinor,
        public ?int $walletTransactionId,
        public array $operationReferences,
        public bool $committed,
        public bool $providerCalls = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'voucher_code' => $this->voucherCode,
            'collection_id' => $this->collectionId,
            'provider' => $this->provider,
            'currency' => $this->currency,
            'amount_minor' => $this->amountMinor,
            'compatibility_balance_minor' => $this->compatibilityBalanceMinor,
            'client_funds_balance_minor' => $this->clientFundsBalanceMinor,
            'divergence_minor' => $this->divergenceMinor,
            'wallet_transaction_id' => $this->walletTransactionId,
            'operation_references' => $this->operationReferences,
            'committed' => $this->committed,
            'provider_calls' => $this->providerCalls,
        ];
    }
}
