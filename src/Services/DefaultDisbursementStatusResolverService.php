<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;

class DefaultDisbursementStatusResolverService implements DisbursementStatusResolverContract
{
    public function resolveFromGatewayResponse(mixed $response): string
    {
        $status = is_array($response)
            ? ($response['status'] ?? null)
            : ($response->status ?? null);

        return $this->normalizeStatus($status);
    }

    public function resolveFromGatewayException(\Throwable $e): string
    {
        $message = strtolower($e->getMessage());

        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return 'unknown';
        }

        if (str_contains($message, 'connection') || str_contains($message, 'network')) {
            return 'unknown';
        }

        return 'failed';
    }

    /**
     * @param  mixed  $status
     */
    public function resolveFromFetchedStatus(mixed $status, array $raw = []): string
    {
        return $this->normalizeStatus($status);
    }

    protected function normalizeStatus(mixed $status): string
    {
        if ($status instanceof PayoutStatus) {
            return match ($status) {
                PayoutStatus::COMPLETED => 'succeeded',
                PayoutStatus::FAILED => 'failed',
                PayoutStatus::PENDING,
                PayoutStatus::PROCESSING => 'pending',
                PayoutStatus::CANCELLED => 'failed',
                PayoutStatus::REFUNDED => 'failed',
                default => 'unknown',
            };
        }

        if (is_string($status)) {
            $value = strtolower(trim($status));

            return match ($value) {
                'success', 'succeeded', 'completed', 'settled', 'paid' => 'succeeded',
                'failed', 'error', 'rejected', 'cancelled', 'canceled' => 'failed',
                'pending', 'processing', 'queued', 'submitted', 'for settlement', 'forsettlement' => 'pending',
                default => 'unknown',
            };
        }

        return 'unknown';
    }
}
