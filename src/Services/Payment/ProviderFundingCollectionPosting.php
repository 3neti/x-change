<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Contracts\VoucherCollectionPostingContract;
use LBHurtado\XChange\Data\Payment\ConfirmedVoucherCollectionData;
use LBHurtado\XChange\Data\Payment\VoucherCollectionPostingData;
use LBHurtado\XChange\Enums\FundingRequestStatus;
use LBHurtado\XChange\Models\FundingRequest;
use LBHurtado\XChange\Services\Funding\StandingFundingRecognitionPolicy;
use LBHurtado\XChange\Services\Treasury\TreasuryInventoryRegistrationService;
use RuntimeException;

final readonly class ProviderFundingCollectionPosting implements VoucherCollectionPostingContract
{
    public function __construct(
        private TreasuryInventoryOperationContract $treasury,
        private TreasuryInventoryRegistrationService $inventoryRegistration,
        private VerifiedTreasuryFundingAllocationContract $allocations,
        private StandingFundingRecognitionPolicy $recognitionPolicy,
    ) {}

    public function driver(): string
    {
        return 'x_change_provider_funding';
    }

    public function post(
        Voucher $voucher,
        ConfirmedVoucherCollectionData $collection,
    ): VoucherCollectionPostingData {
        $request = FundingRequest::query()
            ->where('voucher_id', $voucher->getKey())
            ->lockForUpdate()
            ->sole();

        if ($request->status !== FundingRequestStatus::PayCodeIssued) {
            throw new RuntimeException(
                'Provider-funded Account Funding is not ready for collection.',
            );
        }

        $observationId = data_get(
            $collection->metadata,
            'provider_funding_observation_id',
        );
        $observation = ProviderFundingObservation::query()
            ->whereKey($observationId)
            ->lockForUpdate()
            ->first();

        if (! $observation instanceof ProviderFundingObservation) {
            throw new RuntimeException(
                'Authoritative provider funding evidence is unavailable.',
            );
        }

        $provider = mb_strtolower(trim((string) $collection->provider));
        $currency = mb_strtoupper(trim($collection->currency));
        $matches = $provider !== ''
            && $provider === $observation->provider_code
            && hash_equals(
                (string) $observation->provider_transaction_id,
                (string) $collection->providerTransactionId,
            )
            && $this->recognitionPolicy->accepts($observation)
            && $observation->net_amount_minor === $collection->amountMinor
            && $observation->net_amount_minor === $request->requested_value_minor
            && $observation->currency === $currency
            && $request->currency === $currency
            && data_get($observation->metadata, 'destination_verified') === true
            && data_get($observation->metadata, 'connection_reference')
                === $request->connection_reference;

        if (! $matches) {
            throw new RuntimeException(
                'Provider evidence does not exactly match this Account Funding request.',
            );
        }

        $configuration = $this->treasuryConfiguration($provider);
        $evidenceScope = hash('sha256', implode('|', [
            $provider,
            $observation->provider_transaction_id,
        ]));
        $operationReference = 'funding-recognition:'.$evidenceScope;

        $this->inventoryRegistration->ensure(new TreasuryInventoryData(
            inventoryReference: $configuration['inventory_reference'],
            resourceType: $configuration['resource_type'],
            currency: $currency,
            capacityMinor: 0,
            status: 'requested',
            idempotencyKey: 'register:'.$configuration['inventory_reference'],
            externalReference: $configuration['settlement_resource_reference'],
            metadata: ['provider' => $provider],
        ));

        $recognition = $this->treasury->recognize(
            new TreasuryInventoryRecognitionData(
                operationReference: $operationReference,
                inventoryReference: $configuration['inventory_reference'],
                settlementResourceReference: $configuration['settlement_resource_reference'],
                amountMinor: $collection->amountMinor,
                currency: $currency,
                status: 'requested',
                idempotencyKey: 'funding-recognition-key:'.$evidenceScope,
                effectiveAt: $observation->occurredAtInstant()?->toRfc3339String(),
                externalReference: $provider.':'.$observation->provider_transaction_id,
                metadata: [
                    'provider_transaction_id' => $observation->provider_transaction_id,
                    'funding_request_reference' => $request->reference,
                    'verification_source' => $observation->verification_source,
                ],
            ),
        );
        $allocation = $this->allocations->allocate(
            accountReference: $request->account_reference,
            provider: $provider,
            amountMinor: $collection->amountMinor,
            currency: $currency,
            evidenceReference: $provider.':'.$observation->provider_transaction_id,
            metadata: [
                'source' => 'verified_provider_funding_request',
                'provider' => $provider,
                'provider_transaction_id' => $observation->provider_transaction_id,
                'funding_request_reference' => $request->reference,
            ],
        );

        return new VoucherCollectionPostingData(
            treasuryOperationReference: $recognition->operationReference,
            walletTransactionId: $allocation->destinationTransactionId,
            metadata: [
                'funding_request_reference' => $request->reference,
                'provider_calls' => true,
                'provider_inventory_changed' => true,
                'treasury_position_allocation_reference' => $allocation->allocationOperationReference,
                'treasury_position_transfer_uuid' => $allocation->transferUuid,
            ],
        );
    }

    /**
     * @return array{inventory_reference: string, settlement_resource_reference: string, resource_type: string}
     */
    private function treasuryConfiguration(string $provider): array
    {
        $configuration = config("x-change.funding.providers.{$provider}.treasury");

        if (! is_array($configuration)) {
            throw new RuntimeException(
                'Provider Treasury Inventory is not configured.',
            );
        }

        foreach ([
            'inventory_reference',
            'settlement_resource_reference',
            'resource_type',
        ] as $key) {
            if (! is_string($configuration[$key] ?? null)
                || trim($configuration[$key]) === '') {
                throw new RuntimeException(
                    "Provider Treasury {$key} is not configured.",
                );
            }
        }

        return $configuration;
    }
}
