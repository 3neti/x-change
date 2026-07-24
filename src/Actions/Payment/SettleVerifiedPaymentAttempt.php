<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherCollectionWalletResolverContract;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Enums\PaymentVerificationTrigger;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\VoucherCollectionProgressService;
use LogicException;

class SettleVerifiedPaymentAttempt
{
    public function __construct(
        private readonly VoucherCollectionWalletResolverContract $wallets,
        private readonly RecordVoucherCollection $collections,
        private readonly VoucherCollectionProgressService $progress,
    ) {}

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

            $wallet = $this->wallets->resolve($voucher);
            $amount = $locked->expected_amount_minor / 100;
            $transaction = $wallet->depositFloat($amount, [
                'reason' => 'voucher_collection',
                'payment_attempt_reference' => $locked->reference,
                'voucher_code' => (string) $voucher->code,
                'provider' => $locked->provider_code,
                'provider_transaction_id' => $observation->provider_transaction_id,
                'provider_observation_id' => $observation->getKey(),
            ]);

            $result = new VoucherPaymentResultData(
                voucher_code: (string) $voucher->code,
                status: 'collected',
                amount: $amount,
                currency: $locked->currency,
                provider: $locked->provider_code,
                provider_reference: $locked->reference,
                provider_transaction_id: $observation->provider_transaction_id,
                wallet: [
                    'transaction_id' => $transaction->getKey(),
                ],
                meta: [
                    'payment_attempt_reference' => $locked->reference,
                    'provider_observation_id' => $observation->getKey(),
                    'verification_source' => $observation->verification_source,
                ],
                messages: ['Pay Code payment collected successfully.'],
            );

            $collection = $this->collections->handle(
                voucher: $voucher,
                result: $result,
                payload: [
                    'amount' => $amount,
                    'currency' => $locked->currency,
                    'status' => 'succeeded',
                    'provider' => $locked->provider_code,
                    'provider_reference' => $locked->reference,
                    'provider_transaction_id' => $observation->provider_transaction_id,
                    'idempotency_key' => 'payment-attempt:'.$locked->reference,
                ],
                walletTransaction: $transaction,
            );

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

            $this->progress->persistSummary($voucher);

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
