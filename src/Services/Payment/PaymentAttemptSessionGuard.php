<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use LBHurtado\XChange\Models\PaymentAttempt;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentAttemptSessionGuard
{
    public function hash(string $browserKey): string
    {
        $browserKey = trim($browserKey);

        if ($browserKey === '') {
            throw new NotFoundHttpException;
        }

        $key = config('x-change.payment.attempts.hash_key') ?: config('app.key');

        if (! is_string($key) || trim($key) === '') {
            throw new LogicException('A Payment Attempt hash key must be configured.');
        }

        return hash_hmac('sha256', $browserKey, $key);
    }

    public function assertOwner(PaymentAttempt $attempt, string $browserKey): void
    {
        if (! hash_equals($attempt->session_key_hash, $this->hash($browserKey))) {
            throw new NotFoundHttpException;
        }
    }
}
