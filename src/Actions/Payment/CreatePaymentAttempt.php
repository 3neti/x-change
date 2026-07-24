<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Payment\PaymentAttemptSessionGuard;
use LBHurtado\XChange\Services\VoucherCapabilityGuard;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;
use LogicException;

class CreatePaymentAttempt
{
    public function __construct(
        private readonly VoucherCapabilityGuard $capabilities,
        private readonly VoucherCollectionProgressService $progress,
        private readonly PaymentAttemptSessionGuard $sessions,
    ) {}

    public function handle(
        Voucher $voucher,
        string $provider,
        string $browserKey,
        string $idempotencyKey,
    ): PaymentAttempt {
        $this->capabilities->ensureCanCollect($voucher);

        $provider = strtolower($this->required($provider, 'Provider'));
        $browserKey = $this->required($browserKey, 'Browser session');
        $idempotencyKey = $this->required($idempotencyKey, 'Idempotency key');
        $progress = $this->progress->compute($voucher);

        if ($progress->remaining_to_collect_minor <= 0) {
            throw new LogicException('This Pay Code is already fully paid.');
        }

        if (! (bool) config("x-change.funding.providers.{$provider}.enabled", false)) {
            throw new InvalidArgumentException("Payment provider [{$provider}] is not enabled.");
        }

        $sessionKeyHash = $this->sessions->hash($browserKey);
        $idempotencyKeyHash = $this->sessions->hash($browserKey."\0".$idempotencyKey);
        $fingerprint = hash('sha256', json_encode([
            'voucher_id' => $voucher->getKey(),
            'provider' => $provider,
            'amount_minor' => $progress->remaining_to_collect_minor,
            'currency' => $progress->currency,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        try {
            return DB::transaction(function () use (
                $voucher,
                $provider,
                $progress,
                $sessionKeyHash,
                $idempotencyKeyHash,
                $fingerprint,
            ): PaymentAttempt {
                $attempt = PaymentAttempt::query()->create([
                    'voucher_id' => $voucher->getKey(),
                    'provider_code' => $provider,
                    'expected_amount_minor' => $progress->remaining_to_collect_minor,
                    'currency' => strtoupper($progress->currency),
                    'status' => PaymentAttemptStatus::PendingInstructions,
                    'version' => 1,
                    'session_key_hash' => $sessionKeyHash,
                    'idempotency_key_hash' => $idempotencyKeyHash,
                    'idempotency_fingerprint' => $fingerprint,
                    'expires_at' => now()->addMinutes(
                        (int) config('x-change.payment.attempts.expires_after_minutes', 15),
                    ),
                    'metadata' => [
                        'purpose' => 'voucher_payment',
                    ],
                ]);

                $attempt->events()->create([
                    'sequence' => 1,
                    'event_type' => 'created',
                    'from_status' => null,
                    'to_status' => PaymentAttemptStatus::PendingInstructions,
                    'trigger' => 'payer',
                    'metadata' => [],
                    'occurred_at' => now(),
                ]);

                return $attempt->load('events');
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = PaymentAttempt::query()
                ->where('idempotency_key_hash', $idempotencyKeyHash)
                ->first();

            if (! $existing instanceof PaymentAttempt) {
                throw $exception;
            }

            if (! hash_equals($existing->idempotency_fingerprint, $fingerprint)) {
                throw new LogicException('The payment request was reused with different details.');
            }

            return $existing->load('events');
        }
    }

    private function required(string $value, string $field): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException($field.' is required.');
        }

        return $normalized;
    }
}
