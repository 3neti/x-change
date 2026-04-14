<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\XChange\Contracts\DisbursementStatusFetcherContract;
use LBHurtado\XChange\Data\Reconciliation\DisbursementReconciliationData;
use RuntimeException;

class DefaultDisbursementStatusFetcherService implements DisbursementStatusFetcherContract
{
    public function __construct(
        protected PayoutProvider $provider,
    ) {}

    public function fetch(DisbursementReconciliationData $reconciliation): array
    {
        $transactionId = $reconciliation->provider_transaction_id ?: $reconciliation->provider_reference;

        if (! is_string($transactionId) || trim($transactionId) === '') {
            throw new RuntimeException('No provider transaction identifier available for status fetching.');
        }

        if (method_exists($this->provider, 'checkStatus')) {
            $result = $this->provider->checkStatus($transactionId);

            return $this->normalizeResult($result);
        }

        // Backward-compatible fallback for older provider APIs
        if (method_exists($this->provider, 'status')) {
            $result = $this->provider->status(
                reference: $reconciliation->provider_reference,
                transactionId: $reconciliation->provider_transaction_id,
            );

            return $this->normalizeResult($result);
        }

        throw new RuntimeException('Configured payout provider does not support status fetching.');
    }

    protected function normalizeResult(mixed $result): array
    {
        if (is_array($result)) {
            return $result;
        }

        if (is_object($result) && method_exists($result, 'toArray')) {
            /** @var array<string, mixed> $normalized */
            $normalized = $result->toArray();

            return $normalized;
        }

        return (array) $result;
    }
}
