<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingQrMerchantData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressRequestData;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Data\Funding\StandingFundingAddressBindingData;
use LBHurtado\XChange\Data\Funding\StandingFundingAddressProvisionData;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Exceptions\StandingFundingAddressConflict;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingAddressBindingHead;
use LBHurtado\XChange\Models\StandingFundingAddressBindingRevision;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressBindingResolver;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressProviderRegistry;
use LBHurtado\XChange\Services\Funding\StandingFundingQrArtifactStore;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;

final class ProvisionStandingFundingAddress
{
    private const MaximumDerivationAttempts = 10;

    public function __construct(
        private readonly StandingFundingAddressProviderRegistry $providers,
        private readonly StandingFundingQrArtifactStore $qrArtifacts,
        private readonly AuditLoggerContract $audit,
        private readonly CorrectOrphanedStandingFundingAddressBinding $bindingCorrection,
        private readonly StandingFundingAddressBindingResolver $bindings,
    ) {}

    public function handle(
        Model $owner,
        string $accountReference,
        string $provider,
        FundingAddressPurpose $purpose,
        FundingRecognitionMode $recognitionMode,
        string $currency = 'PHP',
        ?FundingDestinationData $destination = null,
        ?string $routingReference = null,
        ?FundingQrMerchantData $qrMerchant = null,
    ): StandingFundingAddressProvisionData {
        $provider = strtolower(trim($provider));
        $accountReference = trim($accountReference);
        $currency = strtoupper(trim($currency));

        if ($accountReference === '' || $provider === '' || strlen($currency) !== 3) {
            throw new InvalidArgumentException('Standing Funding Address binding details are invalid.');
        }

        $ownerReference = $owner::class.':'.$owner->getKey();
        $bindingKey = hash('sha256', implode("\0", [
            $ownerReference,
            $accountReference,
            $provider,
            $purpose->value,
            $currency,
        ]));

        $existing = StandingFundingAddress::query()
            ->where('binding_key', $bindingKey)
            ->first() ?? $this->bindings->findCurrentByBindingKey($bindingKey);

        if ($existing instanceof StandingFundingAddress) {
            return $this->reopen(
                address: $existing,
                ownerReference: $ownerReference,
                purpose: $purpose,
                qrMerchant: $qrMerchant,
            );
        }

        $provisioned = null;

        for ($counter = 0; $counter < self::MaximumDerivationAttempts; $counter++) {
            $providerAddress = $this->providers->for($provider)->createStandingFundingAddress(
                new StandingFundingAddressRequestData(
                    ownerReference: $ownerReference,
                    accountReference: $accountReference,
                    purpose: $purpose,
                    currency: $currency,
                    destination: $destination,
                    routingReference: $routingReference,
                    derivationCounter: $counter,
                    qrMerchant: $qrMerchant,
                ),
            );
            $fundingAddressHash = hash('sha256', $providerAddress->fundingAddress);

            try {
                $address = DB::transaction(function () use (
                    $owner,
                    $accountReference,
                    $provider,
                    $purpose,
                    $recognitionMode,
                    $currency,
                    $destination,
                    $providerAddress,
                    $bindingKey,
                    $fundingAddressHash,
                ): ?StandingFundingAddress {
                    $existingBinding = StandingFundingAddress::query()
                        ->where('binding_key', $bindingKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existingBinding instanceof StandingFundingAddress) {
                        return $existingBinding;
                    }

                    $addressCollision = StandingFundingAddress::query()
                        ->where('funding_address_hash', $fundingAddressHash)
                        ->lockForUpdate()
                        ->exists();

                    if ($addressCollision) {
                        return null;
                    }

                    $created = StandingFundingAddress::query()->create([
                        'binding_key' => $bindingKey,
                        'owner_type' => $owner::class,
                        'owner_id' => $owner->getKey(),
                        'account_reference' => $accountReference,
                        'provider_code' => $provider,
                        'purpose' => $purpose,
                        'recognition_mode' => $recognitionMode,
                        'status' => FundingAddressStatus::Active,
                        'version' => 1,
                        'derivation_scheme' => $this->displayString(
                            $providerAddress->displayData,
                            'derivation_scheme',
                        ),
                        'derivation_key_id' => $this->displayString(
                            $providerAddress->displayData,
                            'derivation_key_id',
                        ),
                        'derivation_counter' => max(
                            0,
                            (int) data_get($providerAddress->displayData, 'derivation_counter', 0),
                        ),
                        'reference_length' => $this->displayInteger(
                            $providerAddress->displayData,
                            'reference_length',
                        ),
                        'provider_reference' => $providerAddress->providerReference,
                        'funding_address_ciphertext' => $providerAddress->fundingAddress,
                        'funding_address_hash' => $fundingAddressHash,
                        'destination_snapshot_ciphertext' => $destination === null
                            ? null
                            : FundingDestinationSnapshot::fromData($destination),
                        'destination_fingerprint' => $destination?->fingerprint,
                        'currency' => $currency,
                        'minimum_amount_minor' => $this->limit('minimum_amount_minor'),
                        'maximum_amount_minor' => $this->limit('maximum_amount_minor'),
                        'daily_limit_minor' => $this->limit('daily_limit_minor'),
                        'activated_at' => now(),
                        'last_qr_issued_at' => now(),
                        'metadata' => [
                            'reusable' => $providerAddress->reusable,
                            'classification' => 'provider-and-exact-destination',
                        ],
                    ]);

                    $revision = StandingFundingAddressBindingRevision::query()->create([
                        'standing_funding_address_id' => $created->getKey(),
                        'binding_version' => 1,
                        'account_reference_ciphertext' => $accountReference,
                        'account_reference_hash' => hash('sha256', $accountReference),
                        'binding_key' => $bindingKey,
                        'destination_snapshot_ciphertext' => $destination === null
                            ? null
                            : FundingDestinationSnapshot::fromData($destination),
                        'destination_fingerprint' => $destination?->fingerprint,
                        'reason' => 'initial_binding',
                        'evidence_snapshot' => [
                            'schema' => 'x-change.funding-standing-address-binding-revision-evidence.v1',
                            'standing_funding_address_reference' => $created->reference,
                            'role' => 'initial_binding',
                            'account_reference_hash' => hash('sha256', $accountReference),
                        ],
                        'evidence_hash' => hash('sha256', implode('|', [
                            $created->reference,
                            $bindingKey,
                            hash('sha256', $accountReference),
                        ])),
                        'effective_at' => $created->activated_at,
                    ]);
                    StandingFundingAddressBindingHead::query()->create([
                        'standing_funding_address_id' => $created->getKey(),
                        'current_binding_revision_id' => $revision->getKey(),
                    ]);

                    return $created;
                }, attempts: 3);
            } catch (QueryException $exception) {
                $racedBinding = StandingFundingAddress::query()
                    ->where('binding_key', $bindingKey)
                    ->first();

                if ($racedBinding instanceof StandingFundingAddress) {
                    return $this->reopen(
                        address: $racedBinding,
                        ownerReference: $ownerReference,
                        purpose: $purpose,
                        qrMerchant: $qrMerchant,
                    );
                }

                if (! StandingFundingAddress::query()
                    ->where('funding_address_hash', $fundingAddressHash)
                    ->exists()) {
                    throw $exception;
                }

                $address = null;
            }

            if ($address instanceof StandingFundingAddress) {
                if (! hash_equals($address->funding_address_hash, $fundingAddressHash)) {
                    return $this->reopen(
                        address: $address,
                        ownerReference: $ownerReference,
                        purpose: $purpose,
                        qrMerchant: $qrMerchant,
                    );
                }

                $this->persistQrArtifact($address, $providerAddress, $qrMerchant);
                $provisioned = new StandingFundingAddressProvisionData($address, $providerAddress);

                break;
            }

            if (data_get($providerAddress->displayData, 'derivation_scheme') !== 'netbank-account-hmac-v2') {
                $corrected = $this->bindingCorrection->handle(
                    owner: $owner,
                    accountReference: $accountReference,
                    provider: $provider,
                    purpose: $purpose,
                    currency: $currency,
                    destination: $destination,
                    bindingKey: $bindingKey,
                    fundingAddressHash: $fundingAddressHash,
                );

                if ($corrected instanceof StandingFundingAddress) {
                    return $this->reopen(
                        address: $corrected,
                        ownerReference: $ownerReference,
                        purpose: $purpose,
                        qrMerchant: $qrMerchant,
                    );
                }

                $legacyCollision = StandingFundingAddress::query()
                    ->where('funding_address_hash', $fundingAddressHash)
                    ->first();

                if ($legacyCollision instanceof StandingFundingAddress
                    && $legacyCollision->owner_type === $owner::class
                    && (string) $legacyCollision->owner_id === (string) $owner->getKey()
                    && $legacyCollision->receipts()->exists()) {
                    throw StandingFundingAddressConflict::migrationRequired(
                        $legacyCollision->reference,
                    );
                }

                throw StandingFundingAddressConflict::alreadyBound();
            }
        }

        if (! $provisioned instanceof StandingFundingAddressProvisionData) {
            throw new InvalidArgumentException(
                'A unique Standing Funding Address could not be derived safely.',
            );
        }

        $address = $provisioned->address;

        $this->audit->log('funding.standing_address.provisioned', [
            'standing_funding_address_reference' => $address->reference,
            'actor_type' => $owner::class,
            'actor_id' => (string) $owner->getKey(),
            'provider' => $provider,
            'purpose' => $purpose->value,
            'recognition_mode' => $recognitionMode->value,
        ]);

        return $provisioned;
    }

    private function reopen(
        StandingFundingAddress $address,
        string $ownerReference,
        FundingAddressPurpose $purpose,
        ?FundingQrMerchantData $qrMerchant,
    ): StandingFundingAddressProvisionData {
        $lock = Cache::lock(
            'x-change:standing-funding-address:'.$address->getKey(),
            max(1, (int) config('x-change.funding.standing_addresses.lock_seconds', 120)),
        );

        return $lock->block(5, fn (): StandingFundingAddressProvisionData => $this->reopenLocked(
            $address->refresh(),
            $ownerReference,
            $purpose,
            $qrMerchant,
        ));
    }

    private function reopenLocked(
        StandingFundingAddress $address,
        string $ownerReference,
        FundingAddressPurpose $purpose,
        ?FundingQrMerchantData $qrMerchant,
    ): StandingFundingAddressProvisionData {
        if ($address->status !== FundingAddressStatus::Active) {
            throw new InvalidArgumentException('The Standing Funding Address is not active.');
        }

        if (! hash_equals(
            $address->owner_type.':'.$address->owner_id,
            $ownerReference,
        )) {
            throw StandingFundingAddressConflict::alreadyBound();
        }

        $binding = $this->bindings->current($address);
        $fingerprint = $this->qrArtifacts->fingerprint($address, $qrMerchant);
        $artifact = $this->qrArtifacts->find($address, $fingerprint);

        if ($artifact === null) {
            $lock = Cache::lock(
                'x-change:standing-funding-qr:'.hash('sha256', $binding->bindingKey),
                max(1, (int) config(
                    'x-change.funding.standing_addresses.qr_generation_lock_seconds',
                    30,
                )),
            );
            $artifact = $lock->block(
                max(1, (int) config(
                    'x-change.funding.standing_addresses.qr_generation_wait_seconds',
                    5,
                )),
                function () use (
                    $address,
                    $ownerReference,
                    $purpose,
                    $qrMerchant,
                    $fingerprint,
                    $binding,
                ) {
                    $current = $this->qrArtifacts->find($address, $fingerprint);

                    if ($current !== null) {
                        return $current;
                    }

                    $snapshot = $binding->destinationSnapshot;
                    $destination = is_array($snapshot)
                        ? FundingDestinationSnapshot::toData($snapshot)
                        : null;
                    $providerAddress = $this->providers->for($address->provider_code)
                        ->createStandingFundingAddress(new StandingFundingAddressRequestData(
                            ownerReference: $ownerReference,
                            accountReference: $binding->accountReference,
                            purpose: $purpose,
                            currency: $address->currency,
                            destination: $destination,
                            derivationCounter: $address->derivation_counter,
                            existingFundingAddress: $address->funding_address_ciphertext,
                            qrMerchant: $qrMerchant,
                        ));
                    $this->assertProviderBinding($address, $binding, $providerAddress, $purpose);

                    return $this->qrArtifacts->persist(
                        $address,
                        $providerAddress,
                        $fingerprint,
                        $qrMerchant,
                    );
                },
            );
        }

        $providerAddress = $this->qrArtifacts->toProviderData($address, $artifact, $binding);

        $this->assertProviderBinding($address, $binding, $providerAddress, $purpose);

        $address->last_qr_issued_at = now();
        $address->saveQuietly();

        return new StandingFundingAddressProvisionData($address->refresh(), $providerAddress);
    }

    private function persistQrArtifact(
        StandingFundingAddress $address,
        StandingFundingAddressData $providerAddress,
        ?FundingQrMerchantData $qrMerchant,
    ): void {
        $this->qrArtifacts->persist(
            $address,
            $providerAddress,
            $this->qrArtifacts->fingerprint($address, $qrMerchant),
            $qrMerchant,
        );
    }

    private function assertProviderBinding(
        StandingFundingAddress $address,
        StandingFundingAddressBindingData $binding,
        StandingFundingAddressData $providerAddress,
        FundingAddressPurpose $purpose,
    ): void {
        if (! hash_equals($address->funding_address_hash, hash('sha256', $providerAddress->fundingAddress))
            || $address->purpose !== $purpose
            || $providerAddress->purpose !== $purpose
            || $providerAddress->accountReference !== $binding->accountReference
            || $providerAddress->currency !== $address->currency) {
            throw new InvalidArgumentException(
                'The provider could not reopen the immutable Standing Funding Address binding.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $displayData
     */
    private function displayString(array $displayData, string $key): ?string
    {
        $value = data_get($displayData, $key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param  array<string, mixed>  $displayData
     */
    private function displayInteger(array $displayData, string $key): ?int
    {
        $value = data_get($displayData, $key);

        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    private function limit(string $key): ?int
    {
        $value = config("x-change.funding.standing_addresses.limits.{$key}");

        return is_numeric($value) ? max(0, (int) $value) : null;
    }
}
