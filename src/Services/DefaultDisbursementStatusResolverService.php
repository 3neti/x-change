<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;

class DefaultDisbursementStatusResolverService implements DisbursementStatusResolverContract
{
    public function resolveFromGatewayResponse(mixed $response): string
    {
        $status = $response->status ?? null;

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

        $value = is_string($status) ? strtolower($status) : null;

        return match ($value) {
            'success', 'succeeded', 'completed', 'paid' => 'succeeded',
            'failed', 'error', 'rejected', 'cancelled' => 'failed',
            'pending', 'processing', 'queued', 'submitted' => 'pending',
            default => 'unknown',
        };
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
}
