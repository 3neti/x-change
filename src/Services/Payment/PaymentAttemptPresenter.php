<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Models\PaymentAttempt;

final class PaymentAttemptPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(PaymentAttempt $attempt): array
    {
        $instructions = $attempt->instructions_ciphertext;
        $qr = is_array($instructions) ? data_get($instructions, 'qr_code') : null;

        return [
            'reference' => $attempt->reference,
            'status' => $attempt->status->value,
            'provider' => $attempt->provider_code,
            'amount_minor' => $attempt->expected_amount_minor,
            'currency' => $attempt->currency,
            'expires_at' => $attempt->expires_at?->toIso8601String(),
            'last_checked_at' => $attempt->last_checked_at?->toIso8601String(),
            'can_check' => $attempt->status === PaymentAttemptStatus::AwaitingPayment,
            'qr_code' => is_array($qr) ? [
                'mime_type' => data_get($qr, 'mime_type'),
                'base64_payload' => data_get($qr, 'base64_payload'),
                'qr_mode' => data_get($qr, 'qr_mode'),
                'transaction_type' => data_get($qr, 'transaction_type'),
                'embedded_amount' => (bool) data_get($qr, 'embedded_amount', false),
            ] : null,
        ];
    }
}
