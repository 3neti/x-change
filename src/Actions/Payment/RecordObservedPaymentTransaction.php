<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Data\Payment\ObservedPaymentTransactionData;
use LBHurtado\XChange\Data\Payment\PaymentObservationNoticeData;
use LBHurtado\XChange\Events\PaymentTransactionObserved;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Payment\PaymentObservationJournal;

class RecordObservedPaymentTransaction
{
    public function __construct(private readonly PaymentObservationJournal $journal) {}

    public function handle(PaymentAttempt $attempt, ObservedPaymentTransactionData $payment): ObservedPaymentTransaction
    {
        $transactionId = trim($payment->providerTransactionId);

        if ($transactionId === '' || $payment->amountMinor < 0 || trim($payment->currency) === '' || trim($payment->providerStatus) === '') {
            throw new \InvalidArgumentException('A provider transaction ID, valid amount, currency, and status are required.');
        }

        return DB::transaction(function () use ($attempt, $payment, $transactionId): ObservedPaymentTransaction {
            PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());

            $transaction = ObservedPaymentTransaction::query()->createOrFirst(
                [
                    'provider_code' => $attempt->provider_code,
                    'provider_transaction_hash' => hash('sha256', $transactionId),
                ],
                [
                    'payment_attempt_id' => $attempt->getKey(),
                    'provider_transaction_id_ciphertext' => $transactionId,
                    'amount_minor' => $payment->amountMinor,
                    'currency' => strtoupper($payment->currency),
                    'provider_status' => $payment->providerStatus,
                    'settlement_rail' => $payment->settlementRail,
                    'payer_name_ciphertext' => $payment->payerName,
                    'payer_account_ciphertext' => $payment->payerAccountNumber,
                    'payer_institution_ciphertext' => $payment->payerInstitutionCode,
                    'payer_mobile_ciphertext' => $payment->payerMobile,
                    'occurred_at' => $payment->occurredAt,
                    'settled_at' => $payment->settledAt,
                    'observed_at' => now(),
                ],
            );

            if ($transaction->payment_attempt_id !== $attempt->getKey()) {
                throw new \LogicException('Provider transaction is already attributed to another Payment Attempt.');
            }

            if ($transaction->amount_minor !== $payment->amountMinor || $transaction->currency !== strtoupper($payment->currency)) {
                throw new \LogicException('Provider transaction evidence changed its amount or currency.');
            }

            $normalizedStatus = strtolower(trim($payment->providerStatus));
            $latestStatus = $transaction->statuses()->reorder()->orderByDesc('id')->first();
            $status = $latestStatus?->provider_status === $normalizedStatus
                ? $latestStatus
                : $transaction->statuses()->create([
                    'provider_status' => $normalizedStatus,
                    'observed_at' => now(),
                ]);

            if ($status->wasRecentlyCreated) {
                $this->journal->record($transaction, $status);
                $owner = $attempt->voucher->owner;

                if ($owner !== null) {
                    PaymentTransactionObserved::dispatch(
                        $owner::class,
                        (string) $owner->getKey(),
                        new PaymentObservationNoticeData(
                            paymentAttemptId: (int) $attempt->getKey(),
                            transactionId: (int) $transaction->getKey(),
                            statusId: (int) $status->getKey(),
                            providerStatus: $status->provider_status,
                            reason: $transaction->wasRecentlyCreated
                                ? 'provider_payment_observed'
                                : 'provider_payment_status_changed',
                            occurredAt: $status->observed_at->toIso8601String(),
                        ),
                    );
                }
            }

            return $transaction;
        });
    }
}
