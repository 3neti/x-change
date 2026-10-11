<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Checkout;

use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Data\Funding\ProviderPayerIdentityData;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\PaymentGateway\Funding\NetbankFundingProviderAdapter;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

final readonly class ReverifyCheckoutQrPayer
{
    public function __construct(
        private NetbankFundingProviderAdapter $provider,
        private CheckoutLifecycle $checkouts,
    ) {}

    public function handle(PayCodeIssuanceFundingOrder $order, ProviderFundingObservation $observation): bool
    {
        $intent = $order->fundingIntent;
        $address = trim((string) $intent?->funding_address_ciphertext);

        if ($intent === null || $observation->provider_code !== 'netbank' || $address === '') {
            return false;
        }

        $destination = $intent->destination_snapshot_ciphertext;
        $verification = new FundingVerificationData(
            provider: 'netbank',
            fundingIntentReference: $intent->reference,
            expectedAmountMinor: (int) $intent->expected_amount_minor,
            currency: $intent->currency,
            fundingAddress: $address,
            destination: is_array($destination) ? FundingDestinationData::from($destination) : null,
        );
        $verified = $this->provider->verifyFunding($verification);

        if ($verified->providerTransactionId !== $observation->provider_transaction_id
            || $verified->fundingAddress !== $observation->funding_address
            || $verified->grossAmountMinor !== $observation->gross_amount_minor
            || $verified->currency !== $observation->currency
            || $verified->providerStatus !== 'settled') {
            return false;
        }

        $payment = collect($this->provider->incomingPayments($verification))
            ->first(fn ($payment): bool => $payment->transactionId === $verified->providerTransactionId);

        if ($payment === null
            || $payment->amountMinor !== $verified->grossAmountMinor
            || $payment->currency !== $verified->currency
            || $payment->status !== 'settled'
            || ! in_array(strtoupper(trim((string) $payment->payerInstitutionCode)), ['GXCHPHM2XXX', 'PAPHPHM1XXX', 'GCASH', 'MAYA', 'PAYMAYA'], true)
            || preg_match('/^(?:0|63|\+63)9[0-9]{9}$/', trim((string) $payment->payerAccountNumber)) !== 1) {
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
                verificationSource: 'netbank-vca-transaction-history',
                providerVerified: false,
            ),
        ));
    }
}
