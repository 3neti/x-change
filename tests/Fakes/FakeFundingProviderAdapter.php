<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Tests\Fakes;

use Closure;
use LBHurtado\EmiCore\Contracts\FundingProviderAdapter;
use LBHurtado\EmiCore\Contracts\IncomingPaymentReader;
use LBHurtado\EmiCore\Contracts\OnDemandFundingInstructionProvider;
use LBHurtado\EmiCore\Contracts\ProviderFundingDestinationConfigurator;
use LBHurtado\EmiCore\Contracts\ProviderFundingHistoryRequestFactory;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
use LBHurtado\EmiCore\Data\Funding\FundingQrCodeData;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\IncomingPaymentData;
use LBHurtado\EmiCore\Data\Funding\ProviderEventHintData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingDestinationConfigurationData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingHistoryRequestData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\EmiCore\Data\Funding\WebhookAuthenticationData;
use LBHurtado\PaymentGateway\Funding\NetbankFundingProviderAdapter;
use LBHurtado\XChange\Data\Payment\ObservedPaymentTransactionData;
use Throwable;

class FakeFundingProviderAdapter implements FundingProviderAdapter, IncomingPaymentReader, OnDemandFundingInstructionProvider, ProviderFundingDestinationConfigurator, ProviderFundingHistoryRequestFactory
{
    public int $instructionCalls = 0;

    public ?FundingInstructionsData $instructions = null;

    public ?Throwable $instructionException = null;

    public WebhookAuthenticationData $webhookAuthentication;

    public ?ProviderFundingObservationData $fundingObservation = null;

    public ?FundingVerificationData $lastVerification = null;

    /** @var list<FundingVerificationData> */
    public array $fundingVerifications = [];

    /** @var (Closure(FundingVerificationData): ProviderFundingObservationData)|null */
    public ?Closure $fundingVerificationResolver = null;

    /** @var list<ObservedPaymentTransactionData> */
    public array $incomingPayments = [];

    public int $incomingPaymentCalls = 0;

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

    public function createOnDemandFundingInstructions(FundingInstructionRequestData $request, bool $fixedQrEnabled): FundingInstructionsData
    {
        if ($this->provider !== 'netbank') {
            return $this->createFundingInstructions($request);
        }

        if (! $fixedQrEnabled) {
            return app(NetbankFundingProviderAdapter::class)->createOnDemandFundingInstructions($request, false);
        }

        $mode = strtolower(trim((string) config('x-change.issuance_funding.on_demand.fixed_qr_ph.netbank_mode', 'direct_qr')));
        if (! in_array($mode, ['direct_qr', 'registered_vca'], true)) {
            throw new \InvalidArgumentException("Unsupported NetBank on-demand QR mode [{$mode}].");
        }

        return $this->createFundingInstructions($request);
    }

    public function configureFundingDestination(array $input): ProviderFundingDestinationConfigurationData
    {
        $number = (string) ($input['account_number'] ?? '');
        $name = (string) ($input['account_name'] ?? '');
        $alias = (string) ($input['vca_alias'] ?? '');

        return new ProviderFundingDestinationConfigurationData(
            mode: 'bank_account_link', verificationStatus: 'routing_configured', identityLevel: 'corporate_account_vca',
            capabilities: ['funding', 'vca'], metadata: ['registration_token_lifecycle' => 'ephemeral_per_vca'],
            routingProfile: ['bank_account_number' => $number, 'bank_account_name' => $name, 'vca_alias' => $alias],
            fingerprint: hash('sha256', "{$this->provider}|{$number}|{$alias}"),
            displayReference: '•••• '.substr($number, -4)." · VCA {$alias}",
        );
    }

    public function createFundingHistoryRequest(ProviderFundingHistoryRequestData $request): ?FundingVerificationData
    {
        $account = trim((string) config('payment-gateway.netbank.funding.corporate_account_number'));
        $address = preg_replace('/\D+/', '', $account) ?? '';
        if (strlen($address) < 12) {
            return null;
        }

        return new FundingVerificationData(
            provider: $request->provider, fundingIntentReference: $request->reference,
            expectedAmountMinor: $request->expectedAmountMinor, currency: $request->currency,
            fundingAddress: $address,
            destination: new FundingDestinationData(provider: $request->provider, mode: 'shared', destinationType: 'bank_account',
                accountReference: 'treasury-connection:'.$request->connectionReference, displayReference: 'configured',
                fingerprint: hash('sha256', $account), verificationStatus: 'configured', bankAccountNumber: $account),
            observedAfter: $request->observedAfter, observedBefore: $request->observedBefore,
        );
    }

    public function createFundingInstructions(FundingInstructionRequestData $request): FundingInstructionsData
    {
        $this->instructionCalls++;

        if ($this->instructionException instanceof Throwable) {
            throw $this->instructionException;
        }

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
        $this->fundingVerifications[] = $verification;

        if ($this->fundingVerificationResolver instanceof Closure) {
            return ($this->fundingVerificationResolver)($verification);
        }

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

    /** @return list<IncomingPaymentData> */
    public function incomingPayments(FundingVerificationData $verification): array
    {
        $this->lastVerification = $verification;
        $this->incomingPaymentCalls++;

        return array_map(static fn (ObservedPaymentTransactionData $payment): IncomingPaymentData => new IncomingPaymentData(
            transactionId: $payment->providerTransactionId,
            amountMinor: $payment->amountMinor,
            currency: $payment->currency,
            status: $payment->providerStatus,
            occurredAt: $payment->occurredAt === null ? null : \DateTimeImmutable::createFromInterface($payment->occurredAt),
            settledAt: $payment->settledAt === null ? null : \DateTimeImmutable::createFromInterface($payment->settledAt),
            referenceId: null,
            settlementRail: $payment->settlementRail,
            payerName: $payment->payerName,
            payerAccountNumber: $payment->payerAccountNumber,
            payerInstitutionCode: $payment->payerInstitutionCode,
            payerMobile: $payment->payerMobile,
        ), $this->incomingPayments);
    }
}
