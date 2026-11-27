<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Data\Funding\StandingFundingObservationRequestData;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Data\Funding\StandingFundingAddressBindingData;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingAddressWatermark;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;

/**
 * Establishes an immutable provider-led boundary for dedicated NetBank funding.
 * Provider transaction identities are authoritative; provider timestamps are audit data.
 */
final readonly class StandingFundingLedgerWatermark
{
    public function __construct(
        private StandingFundingAddressProviderRegistry $providers,
        private StandingFundingAddressBindingResolver $bindings,
    ) {}

    public function capture(StandingFundingAddress $address): int
    {
        return $this->captureExcept($address, []);
    }

    /** @param list<string> $excludedProviderTransactionIds */
    public function captureExcept(
        StandingFundingAddress $address,
        array $excludedProviderTransactionIds,
    ): int
    {
        $binding = $this->bindings->current($address);
        $destination = is_array($binding->destinationSnapshot)
            ? FundingDestinationSnapshot::toData($binding->destinationSnapshot)
            : null;
        $observations = $this->providers->for($address->provider_code)->observeStandingFundingAddress(
            new StandingFundingObservationRequestData(
                fundingAddress: $address->funding_address_ciphertext,
                accountReference: $binding->accountReference,
                purpose: $address->purpose,
                currency: $address->currency,
                verificationSource: 'commissioning-ledger-watermark',
                destination: $destination,
            ),
        );

        return DB::transaction(function () use ($address, $observations, $excludedProviderTransactionIds): int {
            $created = 0;

            $excluded = array_fill_keys(array_map('strval', $excludedProviderTransactionIds), true);

            foreach ($observations as $observation) {
                if (isset($excluded[$observation->providerTransactionId])
                    || mb_strtolower(trim($observation->providerStatus)) !== 'settled'
                    || $observation->currency !== $address->currency
                    || $observation->grossAmountMinor <= 0
                    || $observation->netAmountMinor <= 0) {
                    continue;
                }
                $key = $this->transactionKey($observation->provider, $observation->providerTransactionId);
                $watermark = StandingFundingAddressWatermark::query()->firstOrCreate([
                    'standing_funding_address_id' => $address->getKey(),
                    'provider_transaction_key' => $key,
                ], [
                    'provider_transaction_id' => $observation->providerTransactionId,
                    'baselined_at' => now(),
                ]);
                $created += $watermark->wasRecentlyCreated ? 1 : 0;
            }

            $metadata = $address->metadata ?? [];
            $metadata['ledger_watermark'] = [
                'status' => 'captured',
                'captured_at' => now()->toRfc3339String(),
                'provider_transaction_count' => StandingFundingAddressWatermark::query()
                    ->where('standing_funding_address_id', $address->getKey())
                    ->count(),
            ];
            $address->forceFill(['metadata' => $metadata])->saveQuietly();

            return $created;
        }, attempts: 3);
    }

    public function permitsTimestampAnomaly(
        StandingFundingAddress $address,
        ProviderFundingObservation $observation,
    ): bool {
        if (! $this->isDedicatedAutomatic($address)) {
            return false;
        }
        if (data_get($address->metadata, 'ledger_watermark.status') !== 'captured') {
            return false;
        }

        return ! $this->isBaselined($address, $observation);
    }

    public function isBaselined(
        StandingFundingAddress $address,
        ProviderFundingObservation $observation,
    ): bool {
        return StandingFundingAddressWatermark::query()
            ->where('standing_funding_address_id', $address->getKey())
            ->where('provider_transaction_key', $this->transactionKey(
                $observation->provider_code,
                $observation->provider_transaction_id,
            ))
            ->exists();
    }

    public function isDedicatedAutomatic(StandingFundingAddress $address): bool
    {
        if ($address->provider_code !== 'netbank'
            || $address->recognition_mode !== FundingRecognitionMode::Automatic) {
            return false;
        }

        $binding = $this->bindings->current($address);

        return data_get($binding->destinationSnapshot, 'mode') === 'dedicated';
    }

    public function binding(StandingFundingAddress $address): StandingFundingAddressBindingData
    {
        return $this->bindings->current($address);
    }

    private function transactionKey(string $provider, string $providerTransactionId): string
    {
        return hash('sha256', strtolower(trim($provider))."\0".trim($providerTransactionId));
    }
}
