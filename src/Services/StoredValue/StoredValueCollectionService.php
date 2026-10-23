<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\StoredValue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeRequestData;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationProofData;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryAllocationReadModelContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryAllocationReadModelQueryData;
use LBHurtado\XChange\Contracts\AppendableEventStoreContract;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperation;
use LBHurtado\XChange\Models\PartnerApiProductionMandate;
use LBHurtado\XChange\Models\StoredValueCollection;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final readonly class StoredValueCollectionService
{
    public function __construct(
        private OtpChallengeGateway $otp,
        private ExecutionEngine $engine,
        private TreasuryAllocationReadModelContract $balances,
        private AppendableEventStoreContract $events,
        private ?StoredValueHolderMobile $holderMobile = null,
    ) {}

    /** @return array<string, mixed> */
    public function start(
        Model $collector,
        string $channel,
        string $rawCode,
        int $amountMinor,
        string $currency,
        string $idempotencyKey,
        ?string $sessionHash = null,
    ): array {
        $this->assertCollector($collector, $channel, $amountMinor, $currency);
        $code = strtoupper(trim($rawCode));
        if (str_contains($code, '|')) {
            if (! preg_match('/^\|\|\s+([A-Z0-9]+(?:-[A-Z0-9]+)*)\s+\|\|$/D', $code, $matches)) {
                throw ValidationException::withMessages(['code' => ['Scan the complete || Pay Code || mark.']]);
            }
            $code = $matches[1];
        }
        if (! preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/D', $code)) {
            throw ValidationException::withMessages(['code' => ['Enter a valid Pay Code.']]);
        }
        $binding = StoredValueHolderBinding::query()
            ->with(['voucher', 'holder'])
            ->whereHas('voucher', fn ($query) => $query->where('code', $code))
            ->where('status', 'active')
            ->first() ?? throw new NotFoundHttpException;
        $this->assertBinding($binding, $currency);
        $mobile = $this->verifiedMobile($binding);
        $keyHash = $this->hash($idempotencyKey);
        $requestHash = $this->hash(implode("\0", [
            $binding->reference, (string) $amountMinor, $currency, $channel,
        ]));
        $scope = [$channel, $collector->getMorphClass(), (string) $collector->getKey()];
        $lockKey = 'x-change:sv-collection:'.$this->hash(implode("\0", [...$scope, $idempotencyKey]));

        return Cache::lock($lockKey, 30)->block(5, function () use (
            $binding, $channel, $collector, $sessionHash, $keyHash, $requestHash,
            $amountMinor, $currency, $mobile, $scope,
        ): array {
            $existing = StoredValueCollection::query()
                ->where('collector_channel', $scope[0])
                ->where('collector_type', $scope[1])
                ->where('collector_id', $scope[2])
                ->where('idempotency_key_hash', $keyHash)
                ->first();

            if ($existing instanceof StoredValueCollection) {
                if (! hash_equals($existing->request_hash, $requestHash)
                    || ($channel === 'operations' && ! hash_equals((string) $existing->collector_session_hash, (string) $sessionHash))) {
                    throw ValidationException::withMessages(['Idempotency-Key' => ['This key belongs to another collection.']]);
                }

                if ($existing->status === 'delivery_failed') {
                    $this->deliverChallenge($existing, $mobile);
                }

                return $this->present($existing);
            }

            $collection = StoredValueCollection::query()->create([
                'stored_value_holder_binding_id' => $binding->getKey(),
                'collector_channel' => $channel,
                'collector_type' => $collector->getMorphClass(),
                'collector_id' => (string) $collector->getKey(),
                'collector_session_hash' => $sessionHash,
                'idempotency_key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'status' => 'delivery_pending',
                'holder_mobile_hash' => $this->hash($mobile),
                'expires_at' => now()->utc()->addMinutes(max(1, (int) config('x-change.execution.stored_value.otp.ttl_minutes', 10))),
            ]);

            $this->deliverChallenge($collection, $mobile);

            return $this->present($collection);
        });
    }

    /** @return array<string, mixed> */
    public function verify(Model $collector, string $channel, string $reference, string $code, ?string $sessionHash = null): array
    {
        $lockKey = 'x-change:sv-collection:verify:'.$this->hash($reference);

        return Cache::lock($lockKey, 30)->block(5, fn (): array => $this->verifyLocked($collector, $channel, $reference, $code, $sessionHash));
    }

    /** @return array<string, mixed> */
    private function verifyLocked(Model $collector, string $channel, string $reference, string $code, ?string $sessionHash): array
    {
        $collection = $this->owned($collector, $channel, $reference, $sessionHash);

        if ($collection->status === 'verified' || $collection->status === 'committed') {
            return $this->present($collection);
        }

        if ($collection->status !== 'pending' || $collection->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => ['This collection challenge is no longer active.']]);
        }

        $maxAttempts = max(1, (int) config('x-change.execution.stored_value.otp.max_attempts', 5));
        if ($collection->attempts >= $maxAttempts) {
            throw ValidationException::withMessages(['code' => ['Too many verification attempts.']]);
        }

        $providerReference = (string) $collection->provider_reference_ciphertext;
        if ($providerReference === '' || ! hash_equals((string) $collection->provider_reference_hash, $this->hash($providerReference))) {
            throw ValidationException::withMessages(['code' => ['This collection challenge is incomplete.']]);
        }

        $result = $this->otp->verify($providerReference, $code);
        if (! $result->ok || ! $result->proof instanceof OtpVerificationProofData) {
            $collection->forceFill([
                'attempts' => $collection->attempts + 1,
                'status' => $collection->attempts + 1 >= $maxAttempts ? 'locked' : 'pending',
            ])->save();
            throw ValidationException::withMessages(['code' => ['The verification code is invalid.']]);
        }

        $proof = $result->proof;
        $verifiedAt = $this->validatedProof($collection, $proof, $providerReference);
        $collection->loadMissing('binding.holder', 'binding.voucher');
        if (! hash_equals($collection->holder_mobile_hash, $this->hash($this->verifiedMobile($collection->binding)))) {
            throw ValidationException::withMessages(['code' => ['The holder identity changed.']]);
        }

        return DB::transaction(function () use ($collection, $proof, $verifiedAt): array {
            $locked = StoredValueCollection::query()->lockForUpdate()->findOrFail($collection->getKey());
            if ($locked->status !== 'pending') {
                return $this->present($locked);
            }
            $locked->forceFill([
                'status' => 'verified',
                'verified_at' => $verifiedAt,
                'proof_reference_hash' => $this->hash(implode("\0", [$proof->reference, $proof->purpose, $proof->verified_at])),
            ])->save();

            return $this->present($locked);
        });
    }

    /** @return array<string, mixed> */
    public function commit(Model $collector, string $channel, string $reference, ?string $sessionHash = null): array
    {
        $result = DB::transaction(function () use ($collector, $channel, $reference, $sessionHash): array {
            if ($channel === 'partner' && $collector instanceof PartnerApiClient) {
                $collector = PartnerApiClient::query()->lockForUpdate()->findOrFail($collector->getKey());
            }
            $collection = $this->owned($collector, $channel, $reference, $sessionHash, lock: true);

            if ($collection->status === 'committed') {
                return $this->present($collection);
            }

            if ($collection->status !== 'verified' || $collection->expires_at->isPast()) {
                throw ValidationException::withMessages(['reference' => ['Holder approval is required for this collection.']]);
            }

            $this->assertCollector($collector, $channel, $collection->amount_minor, $collection->currency);
            $collection->loadMissing('binding.holder', 'binding.voucher');
            $binding = $collection->binding;
            $this->assertBinding($binding, $collection->currency);
            if (! hash_equals($collection->holder_mobile_hash, $this->hash($this->verifiedMobile($binding)))) {
                throw ValidationException::withMessages(['reference' => ['The holder identity changed.']]);
            }
            $available = $this->available($binding);
            if ($available < $collection->amount_minor) {
                throw ValidationException::withMessages(['amount_minor' => ['The reusable balance is insufficient.']]);
            }

            $voucher = $binding->voucher;
            $holderMobile = $this->verifiedMobile($binding);
            $result = $this->engine->execute(ExecutionContextData::fromRedemption(
                voucher: $voucher,
                contact: new Contact(['mobile' => '+'.$holderMobile, 'country' => 'PH']),
                voucherCode: (string) $voucher->code,
                meta: [
                    'operation' => 'spend',
                    'amount' => $collection->amount_minor,
                    'otp_verified' => true,
                    'collection_reference' => $collection->reference,
                ],
                correlation: ['execution_id' => 'stored-value-collection:'.$collection->reference],
            ));
            if (! $result->successful) {
                throw ValidationException::withMessages(['amount_minor' => [(string) data_get($result->metadata, 'message', 'The collection was declined.')]]);
            }
            $balanceAfter = data_get($result->metadata, 'remaining_balance');
            $operationReference = data_get($result->metadata, 'operation_reference');
            if (! is_int($balanceAfter) || ! is_string($operationReference) || $operationReference === '') {
                throw ValidationException::withMessages(['reference' => ['Collection evidence is incomplete. Check the same reference before retrying.']]);
            }
            $receipt = [
                'reference' => $collection->reference,
                'status' => 'committed',
                'amount_minor' => $collection->amount_minor,
                'currency' => $collection->currency,
                'balance_after_minor' => $balanceAfter,
                'collected_at' => now()->utc()->toIso8601String(),
            ];
            $collection->forceFill([
                'status' => 'committed',
                'committed_at' => now()->utc(),
                'balance_after_minor' => $balanceAfter,
                'treasury_operation_reference_hash' => $this->hash($operationReference),
                'receipt' => $receipt,
            ])->save();

            return $this->present($collection);
        }, attempts: 5);

        $receipt = $result['data']['receipt'] ?? null;
        if (is_array($receipt)) {
            $this->events->append([
                'id' => 'stored-value-collection:'.$reference.':committed',
                'type' => 'stored_value.collection.committed',
                'status' => 'recorded',
                'actor' => $channel.':'.$collector->getMorphClass().':'.$collector->getKey(),
                'resource_type' => 'stored_value_collection',
                'resource_id' => $reference,
                'correlation_id' => $reference,
                'idempotency_key' => $reference,
                'occurred_at' => $receipt['collected_at'] ?? now()->utc()->toIso8601String(),
                'payload' => [
                    'channel' => $channel,
                    'amount_minor' => $receipt['amount_minor'],
                    'currency' => $receipt['currency'],
                    'balance_after_minor' => $receipt['balance_after_minor'],
                ],
            ]);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function status(Model $collector, string $channel, string $reference, ?string $sessionHash = null): array
    {
        return $this->present($this->owned($collector, $channel, $reference, $sessionHash));
    }

    private function owned(Model $collector, string $channel, string $reference, ?string $sessionHash, bool $lock = false): StoredValueCollection
    {
        $query = StoredValueCollection::query()
            ->where('reference', $reference)
            ->where('collector_channel', $channel)
            ->where('collector_type', $collector->getMorphClass())
            ->where('collector_id', (string) $collector->getKey());
        if ($lock) {
            $query->lockForUpdate();
        }
        $collection = $query->first() ?? throw new NotFoundHttpException;
        if ($channel === 'operations' && ! hash_equals((string) $collection->collector_session_hash, (string) $sessionHash)) {
            throw new NotFoundHttpException;
        }

        return $collection;
    }

    private function assertCollector(Model $collector, string $channel, int $amountMinor, string $currency): void
    {
        if ($amountMinor <= 0 || $currency !== 'PHP' || ! in_array($channel, ['cockpit', 'operations', 'partner'], true)) {
            throw ValidationException::withMessages(['amount_minor' => ['Enter a positive PHP amount.']]);
        }
        if ($channel !== 'partner') {
            return;
        }
        if (! $collector instanceof PartnerApiClient || ! $collector->isActive()
            || ! in_array('stored-value:spend', $collector->scopes, true)
            || data_get($collector->mandate, 'stored_value_spend.enabled') !== true
            || ($collector->environment === 'production' && ! PartnerApiProductionMandate::query()
                ->where('partner_api_client_id', $collector->getKey())
                ->where('status', 'activated')->exists())) {
            throw new NotFoundHttpException;
        }
        $maximum = (int) data_get($collector->mandate, 'stored_value_spend.maximum_amount_minor', 0);
        $daily = (int) data_get($collector->mandate, 'stored_value_spend.daily_amount_minor', 0);
        $currencies = array_map('strtoupper', (array) data_get($collector->mandate, 'stored_value_spend.currencies', []));
        $usedCollections = (int) StoredValueCollection::query()->where('collector_channel', 'partner')
            ->where('collector_type', $collector->getMorphClass())
            ->where('collector_id', (string) $collector->getKey())
            ->where('status', 'committed')
            ->where('currency', $currency)
            ->where('committed_at', '>=', now()->utc()->startOfDay())
            ->sum('amount_minor');
        $usedApi = (int) PartnerApiOperation::query()
            ->where('partner_api_client_id', $collector->getKey())
            ->where('operation', 'stored_value_spent')
            ->where('currency', $currency)
            ->where('occurred_at', '>=', now()->utc()->startOfDay())
            ->sum('principal_minor');
        if (! in_array($currency, $currencies, true) || $amountMinor > $maximum || $daily <= 0
            || $usedCollections + $usedApi + $amountMinor > $daily) {
            throw ValidationException::withMessages(['amount_minor' => ['This collection exceeds the Partner mandate.']]);
        }
    }

    private function assertBinding(StoredValueHolderBinding $binding, string $currency): void
    {
        $voucher = $binding->voucher;
        if ($binding->status !== 'active' || $binding->released_at !== null
            || $voucher->isExpired() || $voucher->isTerminal()
            || data_get($voucher->metadata, 'instructions.execution.driver') !== 'stored_value'
            || $currency !== $binding->currency) {
            throw new NotFoundHttpException;
        }
    }

    private function verifiedMobile(StoredValueHolderBinding $binding): string
    {
        return ($this->holderMobile ?? app(StoredValueHolderMobile::class))->verified($binding);
    }

    private function available(StoredValueHolderBinding $binding): int
    {
        $state = $this->balances->read(new TreasuryAllocationReadModelQueryData(
            allocationReference: $binding->allocation_reference,
            currency: $binding->currency,
        ));
        if (! $state->hasTreasuryFacts) {
            throw new NotFoundHttpException;
        }

        return $state->usableAmountMinor;
    }

    private function validatedProof(StoredValueCollection $collection, OtpVerificationProofData $proof, string $reference): Carbon
    {
        if (! hash_equals($reference, $proof->reference) || ! hash_equals($this->purpose(), $proof->purpose)) {
            throw ValidationException::withMessages(['code' => ['Verification evidence does not match this collection.']]);
        }
        try {
            $verifiedAt = Carbon::parse($proof->verified_at)->utc();
        } catch (Throwable) {
            throw ValidationException::withMessages(['code' => ['Verification evidence is invalid.']]);
        }
        $skew = max(0, (int) config('x-change.execution.stored_value.otp.clock_skew_seconds', 30));
        $ttl = max(1, (int) config('x-change.execution.stored_value.otp.proof_ttl_minutes', 15));
        if ($verifiedAt->greaterThan(now()->utc()->addSeconds($skew))
            || $verifiedAt->lessThan(now()->utc()->subMinutes($ttl))
            || $verifiedAt->lessThan($collection->created_at->subSeconds($skew))
            || $verifiedAt->greaterThan($collection->expires_at->addSeconds($skew))) {
            throw ValidationException::withMessages(['code' => ['Verification evidence is stale.']]);
        }

        return $verifiedAt;
    }

    /** @return array<string, mixed> */
    private function present(StoredValueCollection $collection): array
    {
        $data = [
            'reference' => $collection->reference,
            'status' => $collection->status,
            'amount_minor' => $collection->amount_minor,
            'currency' => $collection->currency,
            'expires_at' => $collection->expires_at->toIso8601String(),
        ];
        if (in_array($collection->status, ['verified', 'committed'], true)) {
            $collection->loadMissing('binding');
            $data['available_minor'] = $collection->status === 'committed'
                ? $collection->balance_after_minor
                : $this->available($collection->binding);
        }
        if ($collection->status === 'committed') {
            $data['balance_after_minor'] = $collection->balance_after_minor;
            $data['receipt'] = $collection->receipt;
        }

        return ['data' => $data];
    }

    private function hash(string $value): string
    {
        $key = config('x-change.execution.stored_value.otp.hash_key') ?: config('app.key');

        return hash_hmac('sha256', $value, (string) $key);
    }

    private function purpose(): string
    {
        return (string) config('x-change.execution.stored_value.otp.purpose', 'stored-value.spend.v1');
    }

    private function deliverChallenge(StoredValueCollection $collection, string $mobile): void
    {
        try {
            $challenge = $this->otp->create(new OtpChallengeRequestData(
                mobile: '+'.$mobile,
                purpose: $this->purpose(),
                client_reference: $collection->reference,
            ));
            $collection->forceFill([
                'provider_reference_ciphertext' => $challenge->reference,
                'provider_reference_hash' => $this->hash($challenge->reference),
                'status' => 'pending',
                'expires_at' => now()->utc()->addSeconds(max(1, $challenge->expires_in)),
            ])->save();
        } catch (Throwable $exception) {
            $collection->forceFill(['status' => 'delivery_failed'])->save();
            throw $exception;
        }
    }
}
