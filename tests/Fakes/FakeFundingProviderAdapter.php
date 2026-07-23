<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Tests\Fakes;

use LBHurtado\EmiCore\Contracts\FundingProviderAdapter;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
use LBHurtado\EmiCore\Data\Funding\FundingQrCodeData;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\ProviderEventHintData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\EmiCore\Data\Funding\WebhookAuthenticationData;

class FakeFundingProviderAdapter implements FundingProviderAdapter
{
    public int $instructionCalls = 0;

    public ?FundingInstructionsData $instructions = null;

    public WebhookAuthenticationData $webhookAuthentication;

    public ?ProviderFundingObservationData $fundingObservation = null;

    public ?FundingVerificationData $lastVerification = null;

    public function __construct(
        private readonly string $provider = 'netbank',
    ) {
        $this->webhookAuthentication = new WebhookAuthenticationData(
            authenticated: true,
            method: 'fake',
        );
    }

    public function providerCode(): string
    {
        return $this->provider;
    }

    public function createFundingInstructions(FundingInstructionRequestData $request): FundingInstructionsData
    {
        $this->instructionCalls++;

        return $this->instructions ?? new FundingInstructionsData(
            provider: $request->provider,
            providerReference: '915001234567890123456',
            amountMinor: $request->amountMinor,
            currency: $request->currency,
            expiresAt: $request->expiresAt,
            fundingAddress: '915001234567890123456',
            displayData: [
                'institution' => 'NetBank',
                'destination_account' => '915001234567890123456',
                'amount_minor' => $request->amountMinor,
                'currency' => $request->currency,
            ],
            qrCode: new FundingQrCodeData(
                mimeType: 'image/png',
                base64Payload: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lDoLpwAAAABJRU5ErkJggg==',
                qrMode: 'dynamic',
                transactionType: 'p2m',
                embeddedAmount: true,
                providerGenerated: true,
            ),
        );
    }

    public function authenticateWebhook(ProviderWebhookRequestData $request): WebhookAuthenticationData
    {
        return $this->webhookAuthentication;
    }

    public function normalizeWebhook(ProviderWebhookReceiptData $receipt): ProviderEventHintData
    {
        return new ProviderEventHintData(eventType: 'fake.funding');
    }

    public function verifyFunding(FundingVerificationData $verification): ProviderFundingObservationData
    {
        $this->lastVerification = $verification;

        return $this->fundingObservation ?? new ProviderFundingObservationData(
            provider: $verification->provider,
            providerTransactionId: 'provider-transaction-123',
            grossAmountMinor: $verification->expectedAmountMinor,
            feeAmountMinor: 50,
            netAmountMinor: $verification->expectedAmountMinor - 50,
            currency: $verification->currency,
            providerStatus: 'settled',
            verificationSource: 'fake-authoritative-api',
            payloadHash: hash('sha256', 'fake-provider-observation'),
            fundingAddress: 'sha256:'.hash('sha256', (string) $verification->fundingAddress),
            occurredAt: new \DateTimeImmutable('2026-07-23T01:05:00+00:00'),
            settledAt: new \DateTimeImmutable('2026-07-23T01:06:00+00:00'),
            webhookReceiptId: $verification->webhookReceiptId,
            metadata: [
                'destination_verified' => true,
            ],
        );
    }
}
