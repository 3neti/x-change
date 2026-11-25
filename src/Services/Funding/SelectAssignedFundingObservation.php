<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use LBHurtado\EmiCore\Contracts\IncomingPaymentReader;
use LBHurtado\EmiCore\Contracts\SpecificIncomingPaymentVerifier;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\IncomingPaymentData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Exceptions\ProviderFundingVerificationIndeterminate;
use LBHurtado\XChange\Models\FundingIntent;

final readonly class SelectAssignedFundingObservation
{
    public function __construct(private FundingProviderAdapterRegistry $providers, private AssignedAccountFundingPolicy $policy) {}

    public function handle(FundingIntent $intent, FundingVerificationData $verification): ProviderFundingObservationData
    {
        $adapter = $this->providers->for($intent->provider_code);
        if (data_get($intent->destination_snapshot_ciphertext, 'metadata.netbank_instruction_mode') !== 'direct_qr'
            || $intent->expires_at === null || $intent->expires_at->isPast() || ! $this->policy->creditsOwner($intent)
            || ! $adapter instanceof IncomingPaymentReader || ! $adapter instanceof SpecificIncomingPaymentVerifier) {
            throw new ProviderFundingVerificationIndeterminate('Assigned funding requires review.');
        }
        $payments = $adapter->incomingPayments($verification);
        if (count($payments) < 2) {
            throw new ProviderFundingVerificationIndeterminate('Assigned funding cannot be attributed from the available observations.');
        }
        $candidates = array_values(array_filter($payments, fn (IncomingPaymentData $payment): bool => strtolower($payment->status) === 'settled' && $payment->occurredAt !== null));
        usort($candidates, fn (IncomingPaymentData $a, IncomingPaymentData $b): int => ($a->occurredAt <=> $b->occurredAt) ?: strcmp($a->transactionId, $b->transactionId));
        if ($candidates === []) {
            throw new ProviderFundingVerificationIndeterminate('No settled assigned funding is available.');
        }
        $first = $candidates[0];
        $observation = $adapter->verifyIncomingPayment($verification, $first->transactionId);
        if ($observation->provider !== $intent->provider_code || $observation->providerTransactionId !== $first->transactionId
            || $observation->providerStatus !== 'settled' || $observation->currency !== $intent->currency
            || $observation->grossAmountMinor <= 0 || $observation->netAmountMinor <= 0
            || $observation->settledAt === null || $observation->occurredAt === null
            || $observation->occurredAt > $intent->expires_at
            || data_get($observation->metadata, 'destination_verified') !== true) {
            throw new ProviderFundingVerificationIndeterminate('The first assigned funding payment requires review.');
        }

        return $observation;
    }
}
