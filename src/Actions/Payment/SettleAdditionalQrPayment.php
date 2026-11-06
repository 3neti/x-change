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
use LBHurtado\XChange\Exceptions\LatePaymentRequiresReview;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Models\FundingEvidenceClaim;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Payment\LatePaymentEligibility;
use LBHurtado\XChange\Services\Payment\ProviderEvidenceLock;
use LBHurtado\XChange\Services\Payment\SpecificPaymentAttribution;
use LBHurtado\XChange\Services\Settlement\DocumentPaymentGate;
use LBHurtado\XChange\Services\Settlement\V2EvidenceEventJournal;
use LogicException;

final readonly class SettleAdditionalQrPayment
{
    public function __construct(
        private ProviderEvidenceLock $evidenceLock,
        private LatePaymentEligibility $eligibility,
        private V2EvidenceEventJournal $events,
        private SpecificPaymentAttribution $attribution,
        private DocumentPaymentGate $documentGate,
    ) {}

    public function handle(ObservedPaymentTransaction $transaction, ProviderFundingObservation $observation, ?int $reviewId = null, ?int $selectedAttemptId = null): VoucherCollection
    {
        return DB::transaction(function () use ($transaction, $observation, $reviewId, $selectedAttemptId): VoucherCollection {
            $transaction = ObservedPaymentTransaction::query()->findOrFail($transaction->getKey());
            if ($selectedAttemptId !== null && $reviewId === null) {
                throw new LatePaymentRequiresReview('The selected attempt requires an operator review.');
            }
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($selectedAttemptId ?? $transaction->payment_attempt_id);
            $observation = ProviderFundingObservation::query()->lockForUpdate()->findOrFail($observation->getKey());
            if ($attempt->status !== PaymentAttemptStatus::Settled || ! $this->attribution->matches($attempt, $transaction, $observation, $reviewId)) {
                throw new LatePaymentRequiresReview('The payment evidence requires review.');
            }

            $this->evidenceLock->acquire($observation->provider_code, $observation->provider_transaction_id);
            $existing = VoucherCollection::query()->where('provider', $observation->provider_code)
                ->where('provider_transaction_id', $observation->provider_transaction_id)->first();
            if ($existing !== null) {
                if ((int) $existing->voucher_id !== (int) $attempt->voucher_id) {
                    throw new LatePaymentRequiresReview('The payment is already associated with another voucher.');
                }

                return $existing;
            }
            if (FundingEvidenceClaim::query()->where('provider_code', $observation->provider_code)
                ->where('provider_transaction_hash', hash('sha256', implode('|', [$observation->provider_code, $observation->provider_transaction_id])))
                ->exists()) {
                throw new LatePaymentRequiresReview('The payment is already associated with funding.');
            }

            $voucher = Voucher::query()->lockForUpdate()->findOrFail($attempt->voucher_id);
            try {
                $this->documentGate->ensureReady($voucher, lockEnvelope: true);
            } catch (VoucherRequiresSettlementEnvelope) {
                throw new LatePaymentRequiresReview('Required payment documents need review.');
            }
            if (data_get($voucher->metadata, 'instructions.metadata.custom.lead_campaign.campaign_reference') !== null
                || ! $this->eligibility->canApply($attempt, $voucher, $observation)) {
                throw new LatePaymentRequiresReview('The payment cannot be uniquely attributed or is no longer eligible.');
            }

            $key = 'provider-payment:'.hash('sha256', implode('|', [$observation->provider_code, $observation->provider_transaction_id]));
            $execution = app(ExecutionEngine::class)->execute(new ExecutionContextData(
                contact: null, voucherCode: (string) $voucher->code,
                meta: [
                    'operation' => 'collect', 'amount_minor' => $attempt->expected_amount_minor,
                    'currency' => $attempt->currency, 'provider' => $attempt->provider_code,
                    'provider_reference' => $key, 'provider_transaction_id' => $observation->provider_transaction_id,
                    'provider_observation_id' => $observation->getKey(), 'verification_source' => $observation->verification_source,
                    'idempotency_key' => $key,
                ],
                voucher: $voucher, instruction: ExecutionInstructionData::from(['driver' => 'payable_collection']),
                correlation: ['execution_id' => hash('sha256', $key)],
            ));
            if (! $execution->successful || (int) ($execution->metadata['voucher_collection_id'] ?? 0) <= 0) {
                throw new LogicException('Verified additional payment collection was rejected.');
            }
            $collection = VoucherCollection::query()->whereKey($execution->metadata['voucher_collection_id'])
                ->where('voucher_id', $voucher->getKey())->where('idempotency_key', $key)->sole();
            $this->events->paymentCollected($voucher->refresh(), $collection);

            return $collection;
        }, 5);
    }
}
