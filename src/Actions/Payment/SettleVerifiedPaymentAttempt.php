<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LogicException;

class SettleVerifiedPaymentAttempt
{
    public function handle(
        PaymentAttempt $attempt,
        PaymentVerificationTrigger $trigger,
    ): PaymentAttempt {
        return DB::transaction(function () use ($attempt, $trigger): PaymentAttempt {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());

            if ($locked->status === PaymentAttemptStatus::Settled) {
                return $locked->load(['events', 'voucher']);
            }

            if ($locked->status !== PaymentAttemptStatus::Verified) {
                throw new LogicException('Only a verified Payment Attempt can settle.');
            }

            $observation = $this->observation($locked);
            $voucher = Voucher::query()->lockForUpdate()->findOrFail($locked->voucher_id);
            $duplicate = VoucherCollection::query()
                ->where('provider', $locked->provider_code)
                ->where('provider_transaction_id', $observation->provider_transaction_id)
                ->first();

            if ($duplicate instanceof VoucherCollection) {
                throw new LogicException('The provider transaction has already been applied.');
            }

            $idempotencyKey = 'payment-attempt:'.$locked->reference;
            $execution = app(ExecutionEngine::class)->execute(new ExecutionContextData(
                contact: null,
                voucherCode: (string) $voucher->code,
                meta: [
                    'operation' => 'collect',
                    'amount_minor' => $locked->expected_amount_minor,
                    'currency' => $locked->currency,
                    'provider' => $locked->provider_code,
                    'provider_reference' => $locked->reference,
                    'provider_transaction_id' => $observation->provider_transaction_id,
                    'provider_observation_id' => $observation->getKey(),
                    'verification_source' => $observation->verification_source,
                    'idempotency_key' => $idempotencyKey,
                ],
                voucher: $voucher,
                instruction: ExecutionInstructionData::from([
                    'driver' => 'payable_collection',
                ]),
                correlation: [
                    'execution_id' => hash('sha256', implode('|', [
                        'x-change.payment-attempt.collection.v1',
                        (string) $locked->reference,
                    ])),
                ],
            ));

            if (! $execution->successful) {
                throw new LogicException('Verified Payment Attempt collection execution was rejected.');
            }

            $collectionId = (int) ($execution->metadata['voucher_collection_id'] ?? 0);

            if ($collectionId <= 0) {
                throw new LogicException('Verified Payment Attempt collection execution returned no durable collection evidence.');
            }

            $collection = VoucherCollection::query()
                ->whereKey($collectionId)
                ->where('voucher_id', $voucher->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->sole();

            $nextVersion = $locked->version + 1;

            $locked->forceFill([
                'status' => PaymentAttemptStatus::Settled,
                'version' => $nextVersion,
                'voucher_collection_id' => $collection->getKey(),
                'settled_at' => now(),
            ])->saveQuietly();

            $locked->events()->create([
                'sequence' => $nextVersion,
                'event_type' => 'voucher_payment_settled',
                'from_status' => PaymentAttemptStatus::Verified,
                'to_status' => PaymentAttemptStatus::Settled,
                'trigger' => $trigger->value,
                'evidence_reference' => 'voucher-collection:'.$collection->getKey(),
                'metadata' => [
                    'provider_observation_id' => $observation->getKey(),
                    'voucher_collection_id' => $collection->getKey(),
                ],
                'occurred_at' => now(),
            ]);

            return $locked->refresh()->load(['events', 'voucher']);
        }, 5);
    }

    private function observation(PaymentAttempt $attempt): ProviderFundingObservation
    {
        $observation = ProviderFundingObservation::query()
            ->whereKey($attempt->matched_observation_id)
            ->lockForUpdate()
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
            throw new LogicException('Authoritative provider evidence no longer matches the Payment Attempt.');
        }

        return $observation;
    }
}
