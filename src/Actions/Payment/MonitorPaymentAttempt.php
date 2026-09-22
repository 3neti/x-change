<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\XChange\Data\Payment\ObservedPaymentTransactionData;
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

        $grace = max(0, (int) config('x-change.payment.monitoring.expiry_grace_seconds', 300));

        if ($attempt->expires_at?->addSeconds($grace)->isPast()) {
            return 0;
        }

        $adapter = $this->providers->for($attempt->provider_code);

        if (! method_exists($adapter, 'incomingPayments')) {
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

            $this->record->handle($attempt, $payment);
            $count++;
        }

        PaymentAttempt::query()->whereKey($attempt->getKey())->update(['last_monitored_at' => now()]);

        return $count;
    }
}
