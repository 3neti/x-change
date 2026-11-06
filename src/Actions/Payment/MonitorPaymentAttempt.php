<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Contracts\IncomingPaymentReader;
use LBHurtado\EmiCore\Contracts\SpecificIncomingPaymentVerifier;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\XChange\Data\Payment\ObservedPaymentTransactionData;
use LBHurtado\XChange\Exceptions\ObservedPaymentEvidenceConflict;
use LBHurtado\XChange\Exceptions\ObservedPaymentOwnershipConflict;
use LBHurtado\XChange\Jobs\Payment\VerifyAdditionalQrPaymentJob;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;
use LogicException;

final class MonitorPaymentAttempt
{
    public function __construct(
        private readonly FundingProviderAdapterRegistry $providers,
        private readonly RecordObservedPaymentTransaction $record,
    ) {}

    public function handle(PaymentAttempt $attempt): int
    {
        if ($attempt->instructions_created_at === null || $attempt->funding_address_ciphertext === null) {
            return 0;
        }

        $days = max(1, (int) config('x-change.payment.attempts.late_reconciliation_days', 7));

        if ($attempt->expires_at?->addDays($days)->isPast()) {
            return 0;
        }

        $adapter = $this->providers->for($attempt->provider_code);

        if (! $adapter instanceof IncomingPaymentReader) {
            throw new LogicException('This funding provider does not support incoming payment monitoring.');
        }

        $verification = new FundingVerificationData(
            provider: $attempt->provider_code,
            fundingIntentReference: $attempt->reference,
            expectedAmountMinor: $attempt->expected_amount_minor,
            currency: $attempt->currency,
            providerRequestId: $attempt->provider_request_id_ciphertext,
            fundingAddress: $attempt->funding_address_ciphertext,
            destination: FundingDestinationSnapshot::toData($attempt->destination_snapshot_ciphertext),
            observedAfter: $attempt->instructions_created_at->toDateTimeImmutable(),
            observedBefore: now()->toDateTimeImmutable(),
        );
        $count = 0;

        foreach ($adapter->incomingPayments($verification) as $incoming) {
            $payment = $incoming instanceof ObservedPaymentTransactionData
                ? $incoming
                : new ObservedPaymentTransactionData(
                    providerTransactionId: $incoming->transactionId,
                    amountMinor: $incoming->amountMinor,
                    currency: $incoming->currency,
                    providerStatus: $incoming->status,
                    occurredAt: $incoming->occurredAt,
                    settledAt: $incoming->settledAt,
                    settlementRail: $incoming->settlementRail,
                    payerName: $incoming->payerName,
                    payerAccountNumber: $incoming->payerAccountNumber,
                    payerInstitutionCode: $incoming->payerInstitutionCode,
                    payerMobile: $incoming->payerMobile,
                );

            try {
                $transaction = $this->record->handle($attempt, $payment);
            } catch (ObservedPaymentOwnershipConflict|ObservedPaymentEvidenceConflict $exception) {
                $existing = ObservedPaymentTransaction::query()->where('provider_code', $attempt->provider_code)
                    ->where('provider_transaction_hash', hash('sha256', trim($payment->providerTransactionId)))->firstOrFail();
                DB::transaction(function () use ($existing, $attempt, $exception): void {
                    DB::table('x_change_payment_transaction_reviews')->insertOrIgnore([
                        'observed_payment_transaction_id' => $existing->getKey(),
                        'reason_code' => $exception instanceof ObservedPaymentOwnershipConflict ? 'payment_observed_on_multiple_qrs' : 'payment_evidence_changed',
                        'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $reviewId = DB::table('x_change_payment_transaction_reviews')->where('observed_payment_transaction_id', $existing->getKey())->value('id');
                    foreach ([$existing->payment_attempt_id, $attempt->getKey()] as $attemptId) {
                        DB::table('x_change_payment_review_attempts')->insertOrIgnore(['review_id' => $reviewId, 'payment_attempt_id' => $attemptId]);
                    }
                }, 3);
                $count++;

                continue;
            }
            if ($adapter instanceof SpecificIncomingPaymentVerifier) {
                VerifyAdditionalQrPaymentJob::dispatch((int) $transaction->getKey(), $attempt->provider_code);
            }
            $count++;
        }

        PaymentAttempt::query()->whereKey($attempt->getKey())->update(['last_monitored_at' => now()]);

        return $count;
    }
}
