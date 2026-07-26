<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Payment;

final readonly class ConfirmedVoucherCollectionData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $amountMinor,
        public string $currency,
        public string $executionDriver,
        public string $authority,
        public string $authorityReference,
        public string $idempotencyKey,
        public ?string $provider = null,
        public ?string $providerReference = null,
        public ?string $providerTransactionId = null,
        public ?string $payerName = null,
        public ?string $payerMobile = null,
        public array $metadata = [],
    ) {}

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            'amount_minor' => $this->amountMinor,
            'currency' => mb_strtoupper(trim($this->currency)),
            'execution_driver' => trim($this->executionDriver),
            'authority' => trim($this->authority),
            'authority_reference' => trim($this->authorityReference),
            'provider' => $this->provider,
            'provider_reference' => $this->providerReference,
            'provider_transaction_id' => $this->providerTransactionId,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
