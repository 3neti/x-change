<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelQueryData;
use LBHurtado\XChange\Enums\PartnerApiProductionMandateStatus;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperation;
use LBHurtado\XChange\Models\PartnerApiProductionMandate;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Models\StoredValueSpendChallenge;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PartnerStoredValueInstrumentService
{
    public function __construct(
        private PartnerApiRequestContext $context,
        private ExecutionEngine $executionEngine,
        private TreasuryAllocationReadModelContract $allocations,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{data: array<string, mixed>, replayed: bool}
     */
    public function spend(string $instrument, array $payload): array
    {
        return DB::transaction(function () use ($instrument, $payload): array {
            $client = PartnerApiClient::query()
                ->with('issuer')
                ->lockForUpdate()
                ->findOrFail($this->context->client()->getKey());
            $this->context->setClient($client);
            $binding = $this->binding($instrument, lock: true);
            $voucher = $binding->voucher;
            $this->assertSpendable($voucher, $binding);

            $amountMinor = (int) $payload['amount_minor'];
            $currency = strtoupper((string) $payload['currency']);
            $headers = (array) Arr::get($payload, '_partner', []);
            $idempotencyKey = trim((string) Arr::get($headers, 'idempotency_key'));
            $challengeReference = trim((string) ($payload['otp_challenge_reference'] ?? ''));
            $requestHash = $this->requestHash(
                $client,
                $binding,
                $amountMinor,
                $currency,
                $challengeReference,
            );
            $existing = PartnerApiOperation::query()
                ->where('partner_api_client_id', $client->getKey())
                ->where('operation', 'stored_value_spent')
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof PartnerApiOperation) {
                if (! is_string($existing->request_hash) || ! hash_equals($existing->request_hash, $requestHash)) {
                    throw ValidationException::withMessages([
                        'Idempotency-Key' => ['This idempotency key was already used for different stored-value facts.'],
                    ]);
                }

                $snapshot = $existing->response_snapshot;

                if (! is_array($snapshot)
                    || data_get($snapshot, 'schema') !== 'x-change.stored-value-transaction.v1') {
                    throw new RuntimeException('Stored value replay evidence is incomplete.');
                }

                return ['data' => $snapshot, 'replayed' => true];
            }

            if ($currency !== $binding->currency) {
                throw ValidationException::withMessages([
                    'currency' => ['Currency must match the presented reusable balance.'],
                ]);
            }

            $challenge = $this->verifiedChallenge(
                client: $client,
                binding: $binding,
                amountMinor: $amountMinor,
                currency: $currency,
                challengeReference: $challengeReference,
            );

            $holder = $binding->holder;

            if (! $holder instanceof Model) {
                throw new NotFoundHttpException;
            }

            $holderMobile = $holder->getAttribute('mobile');

            if (! is_string($holderMobile) || trim($holderMobile) === '') {
                throw new NotFoundHttpException;
            }

            $contact = new Contact(['mobile' => $holderMobile, 'country' => 'PH']);
            $executionId = hash('sha256', implode('|', [
                'x-change.partner-api.stored-value-spend.v1',
                $client->reference,
                $idempotencyKey,
            ]));
            $result = $this->executionEngine->execute(ExecutionContextData::fromRedemption(
                voucher: $voucher,
                contact: $contact,
                voucherCode: (string) $voucher->code,
                meta: array_filter([
                    'operation' => 'spend',
                    'amount' => $amountMinor,
                    'otp_verified' => $challenge instanceof StoredValueSpendChallenge ? true : null,
                ], static fn (mixed $value): bool => $value !== null),
                correlation: ['execution_id' => $executionId],
            ));

            if (! $result->successful) {
                throw ValidationException::withMessages([
                    'amount_minor' => [(string) data_get(
                        $result->metadata,
                        'message',
                        'The reusable-balance spend was rejected.',
                    )],
                ]);
            }

            $rawBalanceAfter = data_get($result->metadata, 'remaining_balance');
            $balanceAfter = is_int($rawBalanceAfter) ? $rawBalanceAfter : null;
            $treasuryOperationReference = (string) data_get($result->metadata, 'operation_reference');

            if ($balanceAfter === null || $balanceAfter < 0 || trim($treasuryOperationReference) === '') {
                throw new RuntimeException('Stored value Treasury result evidence is incomplete.');
            }
            $occurredAt = now()->utc();
            $operation = new PartnerApiOperation([
                'partner_api_client_id' => $client->getKey(),
                'operation' => 'stored_value_spent',
                'idempotency_key' => $idempotencyKey,
                'correlation_id' => Arr::get($headers, 'correlation_id'),
                'subject_reference' => $binding->reference,
                'principal_minor' => $amountMinor,
                'currency' => $currency,
                'request_hash' => $requestHash,
                'balance_after_minor' => $balanceAfter,
                'authority_reference_hash' => hash('sha256', 'partner-api-stored-value:'.$client->reference),
                'treasury_operation_reference_hash' => hash('sha256', $treasuryOperationReference),
                'occurred_at' => $occurredAt,
            ]);
            $operation->reference = (string) str()->ulid();
            $data = $this->transactionData($operation, $binding, $balanceAfter, $occurredAt->toIso8601String());
            $operation->response_snapshot = $data;
            $operation->save();

            if ($challenge instanceof StoredValueSpendChallenge) {
                $challenge->forceFill([
                    'status' => 'consumed',
                    'consumed_partner_api_operation_id' => $operation->getKey(),
                    'consumed_at' => $occurredAt,
                ])->saveQuietly();
            }

            return ['data' => $data, 'replayed' => false];
        }, attempts: 5);
    }

    /** @return array<string, mixed> */
    public function transactions(string $instrument, int $limit): array
    {
        $client = $this->context->client();
        $binding = $this->binding($instrument);
        $this->assertReadableMandate($client, $binding);
        $state = $this->allocations->read(new TreasuryAllocationReadModelQueryData(
            allocationReference: $binding->allocation_reference,
            currency: $binding->currency,
            metadata: ['source' => 'partner_api_stored_value_read'],
        ));

        if (! $state->hasTreasuryFacts) {
            throw new NotFoundHttpException;
        }

        $transactions = PartnerApiOperation::query()
            ->where('partner_api_client_id', $client->getKey())
            ->where('operation', 'stored_value_spent')
            ->where('subject_reference', $binding->reference)
            ->latest('occurred_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (PartnerApiOperation $operation): array => [
                'reference' => $operation->reference,
                'type' => 'spend',
                'amount_minor' => $operation->principal_minor,
                'currency' => $operation->currency,
                'balance_after_minor' => $operation->balance_after_minor,
                'occurred_at' => $operation->occurred_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'schema' => 'x-change.stored-value-transactions.v1',
            'instrument_reference' => $binding->reference,
            'status' => $binding->voucher->isExpired() ? 'expired' : $binding->status,
            'currency' => $binding->currency,
            'available_minor' => $state->usableAmountMinor,
            'transactions' => $transactions,
        ];
    }

    private function binding(string $instrument, bool $lock = false): StoredValueHolderBinding
    {
        $query = StoredValueHolderBinding::query()
            ->with(['voucher', 'holder'])
            ->where('reference', trim($instrument))
            ->where('status', 'active');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first() ?? throw new NotFoundHttpException;
    }

    private function assertSpendable(Voucher $voucher, StoredValueHolderBinding $binding): void
    {
        if ($voucher->isExpired() || $voucher->isTerminal() || $binding->released_at !== null) {
            throw ValidationException::withMessages([
                'instrument' => ['This reusable balance is no longer spendable.'],
            ]);
        }

        if (data_get($voucher->metadata, 'instructions.execution.driver') !== 'stored_value') {
            throw new NotFoundHttpException;
        }
    }

    private function assertReadableMandate(
        PartnerApiClient $client,
        StoredValueHolderBinding $binding,
    ): void {
        if (! $client->isActive()
            || ! in_array('stored-value:read', $client->scopes, true)
            || data_get($client->mandate, 'stored_value_spend.enabled') !== true
            || ! in_array(
                $binding->currency,
                array_map('strtoupper', (array) data_get($client->mandate, 'stored_value_spend.currencies', [])),
                true,
            )
            || ($client->environment === 'production' && ! PartnerApiProductionMandate::query()
                ->where('partner_api_client_id', $client->getKey())
                ->where('status', PartnerApiProductionMandateStatus::Activated)
                ->exists())) {
            throw new NotFoundHttpException;
        }
    }

    private function requestHash(
        PartnerApiClient $client,
        StoredValueHolderBinding $binding,
        int $amountMinor,
        string $currency,
        string $challengeReference,
    ): string {
        return hash('sha256', json_encode([
            'schema' => 'x-change.partner-stored-value-spend.v1',
            'client' => $client->reference,
            'instrument' => $binding->reference,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'otp_challenge_reference' => $challengeReference !== '' ? $challengeReference : null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function verifiedChallenge(
        PartnerApiClient $client,
        StoredValueHolderBinding $binding,
        int $amountMinor,
        string $currency,
        string $challengeReference,
    ): ?StoredValueSpendChallenge {
        $threshold = (int) data_get(
            $binding->voucher->metadata,
            'instructions.execution.metadata.stored_value.otp_required_above',
            0,
        );
        $required = $threshold > 0 && $amountMinor > $threshold;

        if (! $required && $challengeReference === '') {
            return null;
        }

        if (! $required) {
            throw ValidationException::withMessages([
                'otp_challenge_reference' => ['This spend does not require an OTP challenge.'],
            ]);
        }

        if ($challengeReference === '') {
            throw ValidationException::withMessages([
                'otp_challenge_reference' => ['A verified OTP challenge is required for this spend.'],
            ]);
        }

        $challenge = StoredValueSpendChallenge::query()
            ->where('reference', $challengeReference)
            ->where('partner_api_client_id', $client->getKey())
            ->where('stored_value_holder_binding_id', $binding->getKey())
            ->lockForUpdate()
            ->first();

        if (! $challenge instanceof StoredValueSpendChallenge
            || $challenge->status !== 'verified'
            || $challenge->consumed_at !== null
            || $challenge->verified_at === null
            || $challenge->expires_at === null
            || $challenge->expires_at->isPast()
            || $challenge->proof_reference_hash === null
            || $challenge->amount_minor !== $amountMinor
            || $challenge->currency !== $currency) {
            throw ValidationException::withMessages([
                'otp_challenge_reference' => [
                    'The OTP challenge is not valid for this client, instrument, amount, and currency.',
                ],
            ]);
        }

        return $challenge;
    }

    /** @return array<string, mixed> */
    private function transactionData(
        PartnerApiOperation $operation,
        StoredValueHolderBinding $binding,
        int $balanceAfter,
        string $occurredAt,
    ): array {
        return [
            'schema' => 'x-change.stored-value-transaction.v1',
            'instrument_reference' => $binding->reference,
            'transaction' => [
                'reference' => $operation->reference,
                'type' => 'spend',
                'amount_minor' => $operation->principal_minor,
                'currency' => $operation->currency,
                'balance_after_minor' => $balanceAfter,
                'occurred_at' => $occurredAt,
            ],
        ];
    }
}
