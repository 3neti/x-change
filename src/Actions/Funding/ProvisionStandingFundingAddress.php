<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressRequestData;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Data\Funding\StandingFundingAddressProvisionData;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressProviderRegistry;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;

final class ProvisionStandingFundingAddress
{
    public function __construct(
        private readonly StandingFundingAddressProviderRegistry $providers,
        private readonly AuditLoggerContract $audit,
    ) {}

    public function handle(
        Model $owner,
        string $accountReference,
        string $provider,
        FundingAddressPurpose $purpose,
        FundingRecognitionMode $recognitionMode,
        string $currency = 'PHP',
        ?FundingDestinationData $destination = null,
    ): StandingFundingAddressProvisionData {
        $provider = strtolower(trim($provider));
        $accountReference = trim($accountReference);
        $currency = strtoupper(trim($currency));

        if ($accountReference === '' || $provider === '' || strlen($currency) !== 3) {
            throw new InvalidArgumentException('Standing Funding Address binding details are invalid.');
        }

        $ownerReference = $owner::class.':'.$owner->getKey();
        $providerAddress = $this->providers->for($provider)->createStandingFundingAddress(
            new StandingFundingAddressRequestData(
                ownerReference: $ownerReference,
                accountReference: $accountReference,
                purpose: $purpose,
                currency: $currency,
                destination: $destination,
            ),
        );
        $bindingKey = hash('sha256', implode("\0", [
            $ownerReference,
            $accountReference,
            $provider,
            $purpose->value,
            $currency,
        ]));
        $fundingAddressHash = hash('sha256', $providerAddress->fundingAddress);

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
        ): StandingFundingAddress {
            $address = StandingFundingAddress::query()
                ->where('binding_key', $bindingKey)
                ->lockForUpdate()
                ->first();

            if ($address instanceof StandingFundingAddress) {
                if (! hash_equals($address->funding_address_hash, $fundingAddressHash)
                    || $address->purpose !== $purpose
                    || $address->provider_code !== $provider
                    || $address->account_reference !== $accountReference
                    || $address->currency !== $currency) {
                    throw new InvalidArgumentException(
                        'The provider returned a different address for an immutable Standing Funding Address binding.',
                    );
                }

                $address->last_qr_issued_at = now();
                $address->saveQuietly();

                return $address->refresh();
            }

            return StandingFundingAddress::query()->create([
                'binding_key' => $bindingKey,
                'owner_type' => $owner::class,
                'owner_id' => $owner->getKey(),
                'account_reference' => $accountReference,
                'provider_code' => $provider,
                'purpose' => $purpose,
                'recognition_mode' => $recognitionMode,
                'status' => FundingAddressStatus::Active,
                'version' => 1,
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
        }, attempts: 3);

        $this->audit->log('funding.standing_address.provisioned', [
            'standing_funding_address_reference' => $address->reference,
            'actor_type' => $owner::class,
            'actor_id' => (string) $owner->getKey(),
            'provider' => $provider,
            'purpose' => $purpose->value,
            'recognition_mode' => $recognitionMode->value,
        ]);

        return new StandingFundingAddressProvisionData($address, $providerAddress);
    }

    private function limit(string $key): ?int
    {
        $value = config("x-change.funding.standing_addresses.limits.{$key}");

        return is_numeric($value) ? max(0, (int) $value) : null;
    }
}
