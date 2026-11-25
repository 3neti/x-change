<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Actions\Funding\RecordProviderFundingObservation;
use LBHurtado\EmiCore\Contracts\IncomingPaymentReader;
use LBHurtado\EmiCore\Contracts\SpecificIncomingPaymentVerifier;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\XChange\Data\Funding\FundingIntentTransitionData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Services\Funding\AssignedAccountFundingPolicy;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;
use LogicException;

class MonitorDirectFundingExceptions
{
    public function __construct(
        private readonly FundingProviderAdapterRegistry $providers,
        private readonly TransitionFundingIntent $transition,
        private readonly RecordProviderFundingObservation $record,
        private readonly OpenFundingSuspenseCase $openCase,
    ) {}

    public function handle(FundingIntent $intent): void
    {
        Cache::lock('x-change:funding-intent-verification:'.$intent->getKey(),
            (int) config('x-change.funding.verification_lock_seconds', 120))
            ->block((int) config('x-change.funding.verification_lock_wait_seconds', 5), function () use ($intent): void {
                $outcome = 'direct_qr_monitor_failed';
                try {
                    $this->monitor(FundingIntent::query()->findOrFail($intent->getKey()));
                    $outcome = 'direct_qr_monitored';
                } finally {
                    DB::table('x_change_funding_reconciliation_checks')->updateOrInsert(
                        ['funding_intent_id' => $intent->getKey()],
                        ['last_checked_at' => now(), 'last_outcome' => $outcome],
                    );
                }
            });
    }

    private function monitor(FundingIntent $intent): void
    {
        $snapshot = $intent->destination_snapshot_ciphertext;
        if ($intent->provider_code !== 'netbank'
            || data_get($snapshot, 'mode') !== 'dedicated'
            || data_get($snapshot, 'metadata.netbank_instruction_mode') !== 'direct_qr'
            || ! in_array($intent->status, [FundingIntentStatus::Expired, FundingIntentStatus::Cancelled, FundingIntentStatus::Settled, FundingIntentStatus::Suspense, FundingIntentStatus::Reversed], true)
            || $intent->instructions_created_at === null) {
            throw new LogicException('Only issued, closed dedicated direct QR funding can be monitored.');
        }

        $adapter = $this->providers->for($intent->provider_code);
        if (! $adapter instanceof IncomingPaymentReader || ! $adapter instanceof SpecificIncomingPaymentVerifier) {
            throw new LogicException('The provider cannot verify individual incoming payments.');
        }

        $skew = max(0, (int) config('x-change.funding.requests.bank_transfer.clock_skew_seconds', 120));
        $verification = new FundingVerificationData(
            provider: $intent->provider_code,
            fundingIntentReference: $intent->reference,
            expectedAmountMinor: $intent->expected_amount_minor,
            currency: $intent->currency,
            providerRequestId: $intent->provider_request_id,
            fundingAddress: $intent->funding_address_ciphertext,
            destination: FundingDestinationSnapshot::toData($snapshot),
            observedAfter: $intent->instructions_created_at->subSeconds($skew)->toDateTimeImmutable(),
            observedBefore: now()->addSeconds($skew)->toDateTimeImmutable(),
        );

        foreach ($adapter->incomingPayments($verification) as $payment) {
            if ($payment->transactionId === $intent->provider_transaction_id) {
                continue;
            }
            $evidence = $adapter->verifyIncomingPayment($verification, $payment->transactionId);
            if ($evidence->provider !== $intent->provider_code
                || $evidence->providerTransactionId !== $payment->transactionId
                || data_get($evidence->metadata, 'destination_verified') !== true) {
                throw new LogicException('Provider evidence does not identify the monitored funding destination.');
            }
            if ($evidence->providerStatus !== 'settled') {
                continue;
            }

            DB::transaction(function () use ($intent, $evidence): void {
                $locked = FundingIntent::query()->lockForUpdate()->findOrFail($intent->getKey());
                if ($locked->suspenseCases()->whereHas('providerFundingObservation', fn ($query) => $query
                    ->where('provider_code', $evidence->provider)
                    ->where('provider_transaction_id', $evidence->providerTransactionId))->exists()) {
                    return;
                }
                if (in_array($locked->status, [FundingIntentStatus::Expired, FundingIntentStatus::Cancelled], true)) {
                    $locked = $this->transition->handle($locked, new FundingIntentTransitionData(
                        status: FundingIntentStatus::Suspense,
                        eventType: 'direct_qr_late_payment_review',
                        actorType: 'system', actorId: 'funding-scheduler', expectedVersion: $locked->version,
                    ));
                }
                if (! in_array($locked->status, [FundingIntentStatus::Suspense, FundingIntentStatus::Settled, FundingIntentStatus::Reversed], true)) {
                    throw new LogicException('Funding status changed during direct QR monitoring.');
                }
                $observation = $this->record->handle($evidence);
                if ($locked->status === FundingIntentStatus::Settled
                    && $locked->expires_at !== null && $locked->expires_at->isFuture()
                    && $observation->currency === $locked->currency && $observation->occurred_at !== null
                    && $observation->occurred_at->lessThanOrEqualTo($locked->expires_at)
                    && app(AssignedAccountFundingPolicy::class)->creditsOwner($locked)) {
                    app(CreditAdditionalAssignedFunding::class)->handle($locked, $observation);

                    return;
                }
                $this->openCase->handle(provider: $locked->provider_code, reasonCode: 'direct_qr_unexpected_payment',
                    intent: $locked, observation: $observation, details: ['automatic_credit' => false]);
            }, attempts: 3);
        }

    }
}
