<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Checkout;

use LBHurtado\EmiCore\Contracts\IncomingPaymentReader;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Data\Funding\ProviderPayerIdentityData;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\Settlement\CampaignWalletPayerMobile;

final readonly class ReverifyCheckoutQrPayer
{
    public function __construct(
        private FundingProviderAdapterRegistry $providers,
        private CheckoutLifecycle $checkouts,
        private CampaignWalletPayerMobile $payerMobile,
    ) {}

    public function handle(PayCodeIssuanceFundingOrder $order, ProviderFundingObservation $observation): bool
    {
        $intent = $order->fundingIntent;
        $address = trim((string) $intent?->funding_address_ciphertext);

        if ($intent === null || $observation->provider_code !== $intent->provider_code || $address === '') {
            return false;
        }

        $destination = $intent->destination_snapshot_ciphertext;
        $verification = new FundingVerificationData(
            provider: $intent->provider_code,
            fundingIntentReference: $intent->reference,
            expectedAmountMinor: (int) $intent->expected_amount_minor,
            currency: $intent->currency,
            fundingAddress: $address,
            destination: is_array($destination) ? FundingDestinationData::from($destination) : null,
        );
        $provider = $this->providers->for($intent->provider_code);
        if (! $provider instanceof IncomingPaymentReader) {
            return false;
        }
        $verified = $provider->verifyFunding($verification);

        if ($verified->providerTransactionId !== $observation->provider_transaction_id
            || $verified->fundingAddress !== $observation->funding_address
            || $verified->grossAmountMinor !== $observation->gross_amount_minor
            || $verified->currency !== $observation->currency
            || $verified->providerStatus !== 'settled') {
            return false;
        }

        $payment = collect($provider->incomingPayments($verification))
            ->first(fn ($payment): bool => $payment->transactionId === $verified->providerTransactionId);

        if ($payment === null
            || $payment->amountMinor !== $verified->grossAmountMinor
            || $payment->currency !== $verified->currency
            || $payment->status !== 'settled'
            || $this->payerMobile->interpret($intent->provider_code, $payment->payerInstitutionCode, $payment->payerAccountNumber) === null) {
            return false;
        }

        return $this->checkouts->reconcileConfirmedQrContact($order, new ProviderFundingObservationData(
            provider: $verified->provider,
            providerTransactionId: $verified->providerTransactionId,
            grossAmountMinor: $verified->grossAmountMinor,
            feeAmountMinor: $verified->feeAmountMinor,
            netAmountMinor: $verified->netAmountMinor,
            currency: $verified->currency,
            providerStatus: $verified->providerStatus,
            verificationSource: $verified->verificationSource,
            payloadHash: $verified->payloadHash,
            providerOperationId: $verified->providerOperationId,
            requestId: $verified->requestId,
            fundingAddress: $verified->fundingAddress,
            providerAccountReference: $verified->providerAccountReference,
            occurredAt: $verified->occurredAt,
            settledAt: $verified->settledAt,
            webhookReceiptId: $verified->webhookReceiptId,
            metadata: $verified->metadata,
            payerIdentity: new ProviderPayerIdentityData(
                name: $payment->payerName,
                accountNumber: $payment->payerAccountNumber,
                institutionCode: $payment->payerInstitutionCode,
                mobile: $payment->payerMobile,
                verificationSource: $intent->provider_code.'-vca-transaction-history',
                providerVerified: false,
            ),
        ));
    }
}
