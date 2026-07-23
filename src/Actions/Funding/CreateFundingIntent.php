<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Exceptions\FundingIntentConflict;
use LBHurtado\XChange\Models\FundingIntent;

class CreateFundingIntent
{
    public function handle(CreateFundingIntentData $data): FundingIntent
    {
        $accountReference = $this->required($data->accountReference, 'Account reference');
        $provider = strtolower($this->required($data->provider, 'Provider'));
        $currency = strtoupper($this->required($data->currency, 'Currency'));
        $actorType = $this->required($data->actorType, 'Actor type');
        $actorId = $this->required($data->actorId, 'Actor ID');
        $idempotencyKey = $this->required($data->idempotencyKey, 'Idempotency key');

        if ($data->expectedAmountMinor <= 0) {
            throw new InvalidArgumentException('Expected amount must be greater than zero.');
        }

        if (strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a three-letter code.');
        }

        if (! (bool) config("x-change.funding.providers.{$provider}.enabled", false)) {
            throw new InvalidArgumentException("Funding provider [{$provider}] is not enabled.");
        }

        $idempotencyKeyHash = hash('sha256', implode("\0", [$actorType, $actorId, $idempotencyKey]));
        $fingerprint = hash('sha256', json_encode([
            'account_reference' => $accountReference,
            'provider' => $provider,
            'amount_minor' => $data->expectedAmountMinor,
            'currency' => $currency,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        try {
            return DB::transaction(function () use (
                $data,
                $accountReference,
                $provider,
                $currency,
                $actorType,
                $actorId,
                $idempotencyKeyHash,
                $fingerprint,
            ): FundingIntent {
                $intent = FundingIntent::query()->create([
                    'account_reference' => $accountReference,
                    'provider_code' => $provider,
                    'expected_amount_minor' => $data->expectedAmountMinor,
                    'currency' => $currency,
                    'status' => FundingIntentStatus::PendingInstructions,
                    'version' => 1,
                    'idempotency_key_hash' => $idempotencyKeyHash,
                    'idempotency_fingerprint' => $fingerprint,
                    'created_by_type' => $actorType,
                    'created_by_id' => $actorId,
                    'expires_at' => $data->expiresAt,
                    'metadata' => $data->metadata,
                    'destination_snapshot_ciphertext' => $data->destination?->toArray(),
                    'destination_fingerprint' => $data->destination?->fingerprint,
                ]);

                $intent->events()->create([
                    'sequence' => 1,
                    'event_type' => 'created',
                    'from_status' => null,
                    'to_status' => FundingIntentStatus::PendingInstructions,
                    'actor_type' => $actorType,
                    'actor_id' => $actorId,
                    'metadata' => [],
                    'occurred_at' => now(),
                ]);

                return $intent->load('events');
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = FundingIntent::query()
                ->where('idempotency_key_hash', $idempotencyKeyHash)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            if (! hash_equals($existing->idempotency_fingerprint, $fingerprint)) {
                throw FundingIntentConflict::idempotency();
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
