<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\XChange\Data\Funding\CreateFundingRequestData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\FundingRequest;
use RuntimeException;

final class CreateFundingRequest
{
    public function handle(CreateFundingRequestData $data): FundingRequest
    {
        if ($data->requestedValueMinor <= 0) {
            throw new InvalidArgumentException('Requested value must be greater than zero.');
        }

        $currency = mb_strtoupper(trim($data->currency));
        $idempotencyHash = hash('sha256', implode("\0", [
            $data->requesterType,
            $data->requesterId,
            trim($data->idempotencyKey),
        ]));
        $fingerprint = hash('sha256', json_encode([
            'account_reference' => trim($data->accountReference),
            'funding_type' => $data->fundingType->value,
            'requested_value_minor' => $data->requestedValueMinor,
            'currency' => $currency,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        try {
            return DB::transaction(function () use (
                $data,
                $currency,
                $idempotencyHash,
                $fingerprint,
            ): FundingRequest {
                $request = FundingRequest::query()->create([
                    'account_reference' => trim($data->accountReference),
                    'requester_type' => $data->requesterType,
                    'requester_id' => $data->requesterId,
                    'funding_type' => $data->fundingType,
                    'requested_value_minor' => $data->requestedValueMinor,
                    'currency' => $currency,
                    'status' => FundingRequestStatus::Submitted,
                    'version' => 1,
                    'idempotency_key_hash' => $idempotencyHash,
                    'idempotency_fingerprint' => $fingerprint,
                    'description' => trim($data->description),
                    'external_reference_ciphertext' => $data->externalReference,
                    'occurred_on' => $data->occurredOn,
                    'requester_notes_ciphertext' => $data->requesterNotes,
                    'submitted_at' => now(),
                    'metadata' => [
                        'attachments_enabled' => false,
                        'monetary_authority' => 'independent_backing_verification_only',
                    ],
                ]);

                $request->events()->create([
                    'sequence' => 1,
                    'event_type' => 'submitted',
                    'from_status' => null,
                    'to_status' => FundingRequestStatus::Submitted,
                    'actor_type' => $data->requesterType,
                    'actor_id' => $data->requesterId,
                    'metadata' => ['attachments_received' => false],
                    'occurred_at' => now(),
                ]);

                return $request->load('events');
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = FundingRequest::query()
                ->where('idempotency_key_hash', $idempotencyHash)
                ->first();

            if (! $existing instanceof FundingRequest) {
                throw $exception;
            }

            if (! hash_equals($existing->idempotency_fingerprint, $fingerprint)) {
                throw new RuntimeException(
                    'The Funding Request idempotency key was already used for different instructions.',
                );
            }

            return $existing->load('events');
        }
    }
}
