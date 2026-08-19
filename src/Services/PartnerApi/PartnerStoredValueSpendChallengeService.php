<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeRequestData;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationProofData;
use LBHurtado\XChange\Enums\PartnerApiProductionMandateStatus;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperation;
use LBHurtado\XChange\Models\PartnerApiProductionMandate;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Models\StoredValueSpendChallenge;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final readonly class PartnerStoredValueSpendChallengeService
{
    public function __construct(
        private PartnerApiRequestContext $context,
        private OtpChallengeGateway $otp,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{data: array<string, mixed>, replayed: bool}
     */
    public function create(string $instrument, array $payload): array
    {
        $client = $this->context->client();
        $binding = $this->binding($client, $instrument);
        $amountMinor = (int) $payload['amount_minor'];
        $currency = strtoupper((string) $payload['currency']);
        $idempotencyKey = trim((string) data_get($payload, '_partner.idempotency_key'));
        $idempotencyKeyHash = $this->evidenceHash("idempotency\0".$idempotencyKey);
        $requestHash = $this->requestHash($client, $binding, $amountMinor, $currency);
        $this->assertChallengeRequired($client, $binding, $amountMinor, $currency);
        $lockKey = 'x-change:stored-value-spend-challenge:'.hash(
            'sha256',
            $client->reference."\0".$idempotencyKey,
        );

        return Cache::lock($lockKey, 30)->block(5, function () use (
            $amountMinor,
            $binding,
            $client,
            $currency,
            $idempotencyKeyHash,
            $requestHash,
        ): array {
            $existing = StoredValueSpendChallenge::query()
                ->where('partner_api_client_id', $client->getKey())
                ->where('idempotency_key_hash', $idempotencyKeyHash)
                ->first();

            if ($existing instanceof StoredValueSpendChallenge) {
                $this->assertReplay($existing, $requestHash);
                $existing->setRelation('binding', $binding);

                if (! in_array($existing->status, ['delivery_pending', 'delivery_failed'], true)) {
                    return ['data' => $this->data($existing), 'replayed' => true];
                }

                $challenge = $existing;
                $localReplay = true;
            } else {
                $challenge = $this->createPending(
                    client: $client,
                    binding: $binding,
                    idempotencyKeyHash: $idempotencyKeyHash,
                    requestHash: $requestHash,
                    amountMinor: $amountMinor,
                    currency: $currency,
                );
                $challenge->setRelation('binding', $binding);
                $localReplay = false;
            }

            $mobile = $this->verifiedHolderMobile($binding);

            try {
                $providerChallenge = $this->otp->create(new OtpChallengeRequestData(
                    mobile: '+'.$mobile,
                    purpose: $challenge->purpose,
                    client_reference: $challenge->reference,
                ));

                $challenge->forceFill([
                    'provider_reference_ciphertext' => $providerChallenge->reference,
                    'provider_reference_hash' => $this->evidenceHash($providerChallenge->reference),
                    'status' => 'pending',
                    'expires_at' => now()->utc()->addSeconds(max(1, $providerChallenge->expires_in)),
                ])->saveQuietly();
            } catch (Throwable $exception) {
                $challenge->forceFill(['status' => 'delivery_failed'])->saveQuietly();

                throw $exception;
            }

            $challenge->refresh()->setRelation('binding', $binding);

            return ['data' => $this->data($challenge), 'replayed' => $localReplay];
        });
    }

    /** @return array<string, mixed> */
    public function verify(string $instrument, string $challengeReference, string $code): array
    {
        $client = $this->context->client();
        $binding = $this->binding($client, $instrument);
        $challenge = StoredValueSpendChallenge::query()
            ->where('reference', trim($challengeReference))
            ->where('partner_api_client_id', $client->getKey())
            ->where('stored_value_holder_binding_id', $binding->getKey())
            ->first() ?? throw new NotFoundHttpException;
        $challenge->setRelation('binding', $binding);

        return Cache::lock('x-change:stored-value-spend-challenge:'.$challenge->reference, 30)
            ->block(5, fn (): array => $this->verifyLocked($challenge, $binding, $code));
    }

    /** @return array<string, mixed> */
    private function verifyLocked(
        StoredValueSpendChallenge $challenge,
        StoredValueHolderBinding $binding,
        string $code,
    ): array {
        $challenge->refresh();

        if ($challenge->status === 'verified') {
            return $this->data($challenge);
        }

        if ($challenge->status !== 'pending' || $challenge->consumed_at !== null) {
            throw ValidationException::withMessages([
                'code' => ['This spend challenge is no longer active.'],
            ]);
        }

        $maxAttempts = max(1, (int) config('x-change.execution.stored_value.otp.max_attempts', 5));

        if ($challenge->attempts >= $maxAttempts) {
            $challenge->forceFill(['status' => 'locked'])->saveQuietly();

            throw ValidationException::withMessages([
                'code' => ['Too many verification attempts. Request a new challenge.'],
            ]);
        }

        $clockSkew = max(0, (int) config('x-change.execution.stored_value.otp.clock_skew_seconds', 30));

        if ($challenge->expires_at === null || $challenge->expires_at->addSeconds($clockSkew)->isPast()) {
            $challenge->forceFill(['status' => 'expired'])->saveQuietly();

            throw ValidationException::withMessages([
                'code' => ['This verification code has expired.'],
            ]);
        }

        $mobile = $this->verifiedHolderMobile($binding);

        if (! hash_equals($challenge->mobile_hash, $this->mobileHash($mobile))) {
            $challenge->forceFill(['status' => 'identity_changed'])->saveQuietly();

            throw ValidationException::withMessages([
                'code' => ['The holder identity changed. Request a new challenge.'],
            ]);
        }

        $providerReference = trim((string) $challenge->provider_reference_ciphertext);

        if ($providerReference === ''
            || ! is_string($challenge->provider_reference_hash)
            || ! hash_equals($challenge->provider_reference_hash, $this->evidenceHash($providerReference))) {
            $challenge->forceFill(['status' => 'delivery_failed'])->saveQuietly();

            throw ValidationException::withMessages([
                'code' => ['This spend challenge is incomplete. Request a new challenge.'],
            ]);
        }

        $result = $this->otp->verify($providerReference, $code);

        if (! $result->ok || ! $result->proof instanceof OtpVerificationProofData) {
            $attempts = $challenge->attempts + 1;
            $expired = $result->reason === 'expired';
            $challenge->forceFill([
                'attempts' => $attempts,
                'status' => $expired ? 'expired' : ($attempts >= $maxAttempts ? 'locked' : 'pending'),
            ])->saveQuietly();

            throw ValidationException::withMessages([
                'code' => [$expired ? 'This verification code has expired.' : 'The verification code is invalid.'],
            ]);
        }

        $verifiedAt = $this->validatedProof($challenge, $result->proof, $providerReference);

        return DB::transaction(function () use ($challenge, $result, $verifiedAt): array {
            $locked = StoredValueSpendChallenge::query()->lockForUpdate()->findOrFail($challenge->getKey());
            $locked->setRelation('binding', $challenge->binding);

            if ($locked->status === 'verified') {
                return $this->data($locked);
            }

            if ($locked->status !== 'pending' || $locked->consumed_at !== null) {
                throw ValidationException::withMessages([
                    'code' => ['This spend challenge is no longer active.'],
                ]);
            }

            $locked->forceFill([
                'status' => 'verified',
                'attempts' => max($locked->attempts, (int) ($result->attempts ?? 0)),
                'proof_reference_hash' => $this->evidenceHash(implode("\0", [
                    $result->proof->reference,
                    $result->proof->purpose,
                    $result->proof->verified_at,
                ])),
                'provider_verified_at' => $verifiedAt,
                'verified_at' => now()->utc(),
            ])->saveQuietly();

            $locked->refresh()->setRelation('binding', $challenge->binding);

            return $this->data($locked);
        }, attempts: 5);
    }

    private function createPending(
        PartnerApiClient $client,
        StoredValueHolderBinding $binding,
        string $idempotencyKeyHash,
        string $requestHash,
        int $amountMinor,
        string $currency,
    ): StoredValueSpendChallenge {
        $mobile = $this->verifiedHolderMobile($binding);

        return DB::transaction(function () use (
            $amountMinor,
            $binding,
            $client,
            $currency,
            $idempotencyKeyHash,
            $mobile,
            $requestHash,
        ): StoredValueSpendChallenge {
            PartnerApiClient::query()->whereKey($client->getKey())->lockForUpdate()->firstOrFail();
            StoredValueHolderBinding::query()->whereKey($binding->getKey())->lockForUpdate()->firstOrFail();

            StoredValueSpendChallenge::query()
                ->where('partner_api_client_id', $client->getKey())
                ->where('stored_value_holder_binding_id', $binding->getKey())
                ->whereIn('status', ['delivery_pending', 'pending'])
                ->update(['status' => 'superseded', 'updated_at' => now()->utc()]);

            return StoredValueSpendChallenge::query()->create([
                'partner_api_client_id' => $client->getKey(),
                'stored_value_holder_binding_id' => $binding->getKey(),
                'idempotency_key_hash' => $idempotencyKeyHash,
                'request_hash' => $requestHash,
                'mobile_hash' => $this->mobileHash($mobile),
                'provider' => $this->provider(),
                'purpose' => $this->purpose(),
                'status' => 'delivery_pending',
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'expires_at' => now()->utc()->addMinutes(
                    max(1, (int) config('x-change.execution.stored_value.otp.ttl_minutes', 10)),
                ),
            ]);
        }, attempts: 5);
    }

    private function assertChallengeRequired(
        PartnerApiClient $client,
        StoredValueHolderBinding $binding,
        int $amountMinor,
        string $currency,
    ): void {
        $threshold = (int) data_get(
            $binding->voucher->metadata,
            'instructions.execution.metadata.stored_value.otp_required_above',
            0,
        );
        $maximum = (int) data_get($client->mandate, 'stored_value_spend.maximum_amount_minor', 0);
        $daily = (int) data_get($client->mandate, 'stored_value_spend.daily_amount_minor', 0);
        $currencies = array_map('strtoupper', (array) data_get(
            $client->mandate,
            'stored_value_spend.currencies',
            [],
        ));

        if (! $client->isActive()
            || ! in_array('stored-value:spend', $client->scopes, true)
            || data_get($client->mandate, 'stored_value_spend.enabled') !== true
            || $currency !== $binding->currency
            || ! in_array($currency, $currencies, true)
            || $amountMinor <= 0
            || $amountMinor > $maximum) {
            throw ValidationException::withMessages([
                'amount_minor' => ['This spend is outside the authenticated Partner API mandate.'],
            ]);
        }

        $usedToday = (int) PartnerApiOperation::query()
            ->where('partner_api_client_id', $client->getKey())
            ->where('operation', 'stored_value_spent')
            ->where('currency', $currency)
            ->where('occurred_at', '>=', now()->utc()->startOfDay())
            ->sum('principal_minor');

        if ($daily <= 0 || $usedToday + $amountMinor > $daily) {
            throw ValidationException::withMessages([
                'amount_minor' => ['The authenticated Partner API daily stored value limit is exhausted.'],
            ]);
        }

        if ($threshold <= 0 || $amountMinor <= $threshold) {
            throw ValidationException::withMessages([
                'amount_minor' => ['This spend does not require an OTP challenge.'],
            ]);
        }
    }

    private function binding(PartnerApiClient $client, string $instrument): StoredValueHolderBinding
    {
        if (! $client->isActive()
            || ! in_array('stored-value:spend', $client->scopes, true)
            || data_get($client->mandate, 'stored_value_spend.enabled') !== true
            || ($client->environment === 'production' && ! PartnerApiProductionMandate::query()
                ->where('partner_api_client_id', $client->getKey())
                ->where('status', PartnerApiProductionMandateStatus::Activated)
                ->exists())) {
            throw new NotFoundHttpException;
        }

        $binding = StoredValueHolderBinding::query()
            ->with(['voucher', 'holder'])
            ->where('reference', trim($instrument))
            ->where('status', 'active')
            ->first() ?? throw new NotFoundHttpException;

        if ($binding->voucher->isExpired() || $binding->voucher->isTerminal() || $binding->released_at !== null) {
            throw ValidationException::withMessages([
                'instrument' => ['This reusable balance is no longer spendable.'],
            ]);
        }

        if (data_get($binding->voucher->metadata, 'instructions.execution.driver') !== 'stored_value') {
            throw new NotFoundHttpException;
        }

        return $binding;
    }

    private function verifiedHolderMobile(StoredValueHolderBinding $binding): string
    {
        $holder = $binding->holder;

        if (! $holder instanceof Model || $holder->getAttribute('mobile_verified_at') === null) {
            throw new NotFoundHttpException;
        }

        $mobile = MobileNumber::normalize($holder->getRawOriginal('mobile'));

        return $mobile ?? throw new NotFoundHttpException;
    }

    private function validatedProof(
        StoredValueSpendChallenge $challenge,
        OtpVerificationProofData $proof,
        string $providerReference,
    ): Carbon {
        if (! hash_equals($providerReference, $proof->reference)
            || ! hash_equals($challenge->purpose, $proof->purpose)) {
            throw ValidationException::withMessages([
                'code' => ['The verification provider returned evidence for another challenge.'],
            ]);
        }

        try {
            $verifiedAt = Carbon::parse($proof->verified_at)->utc();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'code' => ['The verification provider returned invalid evidence.'],
            ]);
        }

        $now = now()->utc();
        $clockSkew = max(0, (int) config('x-change.execution.stored_value.otp.clock_skew_seconds', 30));
        $proofTtl = max(1, (int) config('x-change.execution.stored_value.otp.proof_ttl_minutes', 15));

        if ($verifiedAt->greaterThan($now->copy()->addSeconds($clockSkew))
            || $verifiedAt->lessThan($now->copy()->subMinutes($proofTtl))
            || $challenge->created_at === null
            || $verifiedAt->lessThan($challenge->created_at->copy()->subSeconds($clockSkew))
            || $challenge->expires_at === null
            || $verifiedAt->greaterThan($challenge->expires_at->addSeconds($clockSkew))) {
            throw ValidationException::withMessages([
                'code' => ['The verification provider returned stale evidence.'],
            ]);
        }

        return $verifiedAt;
    }

    private function assertReplay(StoredValueSpendChallenge $challenge, string $requestHash): void
    {
        if (! hash_equals($challenge->request_hash, $requestHash)) {
            throw ValidationException::withMessages([
                'Idempotency-Key' => ['This idempotency key was already used for different challenge facts.'],
            ]);
        }
    }

    private function requestHash(
        PartnerApiClient $client,
        StoredValueHolderBinding $binding,
        int $amountMinor,
        string $currency,
    ): string {
        return hash('sha256', json_encode([
            'schema' => 'x-change.partner-stored-value-spend-challenge.v1',
            'client' => $client->reference,
            'instrument' => $binding->reference,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function mobileHash(string $mobile): string
    {
        return hash_hmac('sha256', $mobile, $this->hashKey());
    }

    private function evidenceHash(string $evidence): string
    {
        return hash_hmac('sha256', $evidence, $this->hashKey());
    }

    private function hashKey(): string
    {
        $key = config('x-change.execution.stored_value.otp.hash_key') ?: config('app.key');

        if (! is_string($key) || trim($key) === '') {
            throw new LogicException('A stored value OTP evidence hash key is required.');
        }

        return $key;
    }

    private function provider(): string
    {
        $provider = trim((string) config('otp-handler.driver', 'unavailable'));

        if (in_array($provider, ['', 'unavailable', 'null'], true)) {
            throw ValidationException::withMessages([
                'instrument' => ['Stored value OTP delivery is unavailable.'],
            ]);
        }

        return $provider;
    }

    private function purpose(): string
    {
        $purpose = trim((string) config('x-change.execution.stored_value.otp.purpose', 'stored-value.spend.v1'));

        if ($purpose === '') {
            throw new LogicException('A stored value OTP purpose is required.');
        }

        return $purpose;
    }

    /** @return array<string, mixed> */
    private function data(StoredValueSpendChallenge $challenge): array
    {
        return [
            'schema' => 'x-change.stored-value-spend-challenge.v1',
            'reference' => $challenge->reference,
            'instrument_reference' => $challenge->binding->reference,
            'status' => $challenge->status,
            'amount_minor' => $challenge->amount_minor,
            'currency' => $challenge->currency,
            'expires_at' => $challenge->expires_at?->toIso8601String(),
            'verified_at' => $challenge->verified_at?->toIso8601String(),
        ];
    }
}
