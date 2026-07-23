<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Tests\Fakes;

use LBHurtado\EmiCore\Contracts\FundingProviderAdapter;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
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

    public function __construct(
        private readonly string $provider = 'netbank',
    ) {}

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
        );
    }

    public function authenticateWebhook(ProviderWebhookRequestData $request): WebhookAuthenticationData
    {
        return new WebhookAuthenticationData(authenticated: true, method: 'fake');
    }

    public function normalizeWebhook(ProviderWebhookReceiptData $receipt): ProviderEventHintData
    {
        return new ProviderEventHintData(eventType: 'fake.funding');
    }

    public function verifyFunding(FundingVerificationData $verification): ProviderFundingObservationData
    {
        throw new \LogicException('Funding verification is not configured for this fake.');
    }
}
