<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Actions\Payment\SettleVerifiedPaymentAttempt;
use LBHurtado\XChange\Data\Payment\VerifiedPaymentSettlementRecoveryData;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use RuntimeException;

final readonly class VerifiedPaymentSettlementRecoveryService
{
    public function __construct(
        private SettleVerifiedPaymentAttempt $settle,
        private TreasuryProviderConnectionCatalog $connections,
    ) {}

    /**
     * @param  list<string>  $references
     * @return list<VerifiedPaymentSettlementRecoveryData>
     */
    public function inspect(array $references): array
    {
        return array_map(
            fn (string $reference): VerifiedPaymentSettlementRecoveryData => $this->plan(
                $this->attempt($reference),
                false,
            ),
            $this->references($references),
        );
    }

    /**
     * @param  list<string>  $references
     * @return list<VerifiedPaymentSettlementRecoveryData>
     */
    public function recover(array $references): array
    {
        $references = $this->references($references);

        return DB::transaction(function () use ($references): array {
            $attempts = PaymentAttempt::query()
                ->whereIn('reference', $references)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('reference');

            if ($attempts->count() !== count($references)) {
                throw new RuntimeException('One or more exact Payment Attempt references could not be resolved.');
            }

            Voucher::query()
                ->whereIn('id', $attempts->pluck('voucher_id')->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($references as $reference) {
                $this->plan($attempts->get($reference), false);
            }

            $results = [];

            foreach ($references as $reference) {
                /** @var PaymentAttempt $attempt */
                $attempt = $attempts->get($reference);

                if ($attempt->status === PaymentAttemptStatus::Verified) {
                    $attempt = $this->settle->handle(
                        $attempt,
                        PaymentVerificationTrigger::Operator,
                    );
                }

                $results[] = $this->plan($attempt->refresh(), true);
            }

            return $results;
        }, 5);
    }

    private function attempt(string $reference): PaymentAttempt
    {
        return PaymentAttempt::query()
            ->where('reference', $reference)
            ->with('voucher')
            ->sole();
    }

    private function plan(
        PaymentAttempt $attempt,
        bool $committed,
    ): VerifiedPaymentSettlementRecoveryData {
        if (! in_array($attempt->status, [
            PaymentAttemptStatus::Verified,
            PaymentAttemptStatus::Settled,
        ], true)) {
            throw new RuntimeException('The Payment Attempt is not verified or settled.');
        }

        $observation = ProviderFundingObservation::query()
            ->whereKey($attempt->matched_observation_id)
            ->first();

        $matches = $observation instanceof ProviderFundingObservation
            && $observation->provider_code === $attempt->provider_code
            && $observation->provider_transaction_id === $attempt->provider_transaction_id
            && $observation->provider_status === 'settled'
            && $observation->gross_amount_minor === $attempt->expected_amount_minor
            && $observation->net_amount_minor === $attempt->expected_amount_minor
            && $observation->currency === $attempt->currency
            && $observation->settled_at !== null
            && data_get($observation->metadata, 'destination_verified') === true;

        if (! $matches) {
            throw new RuntimeException('Authoritative provider evidence no longer matches the Payment Attempt.');
        }

        $references = $this->operationReferences($attempt, $observation);
        $present = [
            TreasuryInventoryOperation::query()
                ->where('operation_reference', $references['inventory_recognition'])
                ->exists(),
            TreasuryPositionOperation::query()
                ->where('operation_reference', $references['position_recognition'])
                ->exists(),
            TreasuryPositionOperation::query()
                ->where('operation_reference', $references['position_allocation'])
                ->exists(),
        ];
        $presentCount = count(array_filter($present));
        $collection = VoucherCollection::query()
            ->where('provider', $attempt->provider_code)
            ->where('provider_transaction_id', $observation->provider_transaction_id)
            ->first();

        if ($attempt->status === PaymentAttemptStatus::Verified) {
            if ($presentCount !== 0 || $collection !== null || $attempt->voucher_collection_id !== null) {
                throw new RuntimeException('Partial settlement evidence exists; automatic recovery is refused.');
            }

            $status = 'ready';
        } else {
            $exactCollection = $collection instanceof VoucherCollection
                && $attempt->voucher_collection_id === $collection->getKey()
                && $collection->voucher_id === $attempt->voucher_id
                && $collection->collected_amount_minor === $attempt->expected_amount_minor
                && $collection->currency === $attempt->currency;

            if (! $exactCollection || $presentCount !== 3) {
                throw new RuntimeException('Settled replay evidence is incomplete or conflicting.');
            }

            $status = 'already_settled';
        }

        return new VerifiedPaymentSettlementRecoveryData(
            status: $status,
            attemptReference: (string) $attempt->reference,
            voucherReference: 'voucher:'.$attempt->voucher_id,
            provider: $attempt->provider_code,
            amountMinor: $attempt->expected_amount_minor,
            currency: $attempt->currency,
            observationId: (int) $observation->getKey(),
            providerTransactionHash: hash('sha256', $observation->provider_transaction_id),
            operationReferences: $references,
            committed: $committed,
        );
    }

    /** @return array{inventory_recognition: string, position_recognition: string, position_allocation: string} */
    private function operationReferences(
        PaymentAttempt $attempt,
        ProviderFundingObservation $observation,
    ): array {
        $provider = mb_strtolower(trim($attempt->provider_code));
        $currency = mb_strtoupper(trim($attempt->currency));
        $connections = array_values(array_filter(
            $this->connections->active(),
            static fn ($connection): bool => $connection->provider === $provider
                && $connection->currency === $currency,
        ));

        if (count($connections) !== 1) {
            throw new RuntimeException('Exactly one active Treasury provider connection is required.');
        }

        $providerTransactionId = $observation->provider_transaction_id;
        $inventoryScope = hash('sha256', implode('|', [
            $provider,
            $providerTransactionId,
            $currency,
            (string) $attempt->expected_amount_minor,
        ]));
        $evidenceReference = 'voucher-collection:'.$provider.':'.$providerTransactionId;
        $positionScope = hash('sha256', implode('|', [
            $provider,
            $connections[0]->reference,
            $currency,
            $evidenceReference,
        ]));

        return [
            'inventory_recognition' => 'voucher-collection-recognition:'.$inventoryScope,
            'position_recognition' => 'position-recognition:'.$positionScope,
            'position_allocation' => 'position-allocation:'.$positionScope,
        ];
    }

    /** @param list<string> $references @return list<string> */
    private function references(array $references): array
    {
        $references = array_values(array_unique(array_filter(
            array_map(static fn (string $reference): string => trim($reference), $references),
        )));
        sort($references);

        if ($references === []) {
            throw new RuntimeException('At least one exact Payment Attempt reference is required.');
        }

        foreach ($references as $reference) {
            if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $reference) !== 1) {
                throw new RuntimeException('A Payment Attempt reference is invalid.');
            }
        }

        return $references;
    }
}
