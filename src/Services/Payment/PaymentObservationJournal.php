<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Models\ObservedPaymentStatus;
use LBHurtado\XChange\Models\ObservedPaymentTransaction;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class PaymentObservationJournal
{
    public function __construct(private ExecutionJournalRecorder $recorder) {}

    public function record(ObservedPaymentTransaction $transaction, ObservedPaymentStatus $status): void
    {
        $attempt = $transaction->paymentAttempt;

        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'payment.provider_observed',
            occurredAt: CarbonImmutable::instance($status->observed_at),
            actor: new ExecutionActorData(
                id: $transaction->provider_code,
                type: 'payment_provider',
            ),
            subject: new ExecutionSubjectData(
                id: (string) $attempt->voucher_id,
                type: 'voucher',
                display: 'Pay Code payment observation',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'payment-attempt:'.$attempt->getKey(),
                causationId: 'provider-transaction-hash:'.$transaction->provider_transaction_hash,
                executionId: (string) $status->getKey(),
                metadata: [
                    'payment_attempt_id' => (string) $attempt->getKey(),
                    'observed_transaction_id' => (string) $transaction->getKey(),
                ],
            ),
            idempotencyKey: 'x-change:payment-observation-status:'.$status->getKey(),
            payload: [
                'provider' => $transaction->provider_code,
                'provider_status' => $status->provider_status,
                'provider_calls' => false,
                'provider_inventory_changed' => false,
                'treasury_position_changed' => false,
                'voucher_collection_changed' => false,
            ],
            money: new ExecutionMoneyData(
                currency: $transaction->currency,
                minorAmount: $transaction->amount_minor,
            ),
            metadata: [
                'schema' => 'x-change.payment-provider-observation.v1',
                'domain' => 'payment_observation',
                'source' => 'verified_provider_history',
                'accounting_authority' => 'none',
            ],
        ));
    }
}
