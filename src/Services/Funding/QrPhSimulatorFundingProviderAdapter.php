<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use LBHurtado\EmiCore\Contracts\FundingProviderAdapter;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\ProviderEventHintData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Data\Funding\ProviderPayerIdentityData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\EmiCore\Data\Funding\WebhookAuthenticationData;
use LBHurtado\EmiCore\Exceptions\ProviderFundingNotObserved;
use LBHurtado\XChange\Models\SimulatedFundingTransaction;
use LBHurtado\XChange\Support\Funding\QrPhFundingSimulatorGuard;
use LogicException;

class QrPhSimulatorFundingProviderAdapter implements FundingProviderAdapter
{
    private const Provider = 'qrph_simulator';

    public function __construct(
        private readonly QrPhFundingSimulatorGuard $guard,
    ) {}

    public function providerCode(): string
    {
        return self::Provider;
    }

    public function createFundingInstructions(FundingInstructionRequestData $request): FundingInstructionsData
    {
        $this->guard->assertAvailable();
        $this->assertProvider($request->provider);

        if ($request->amountMinor <= 0 || strtoupper($request->currency) !== 'PHP') {
            throw new InvalidArgumentException('QR Ph simulation requires a positive PHP amount.');
        }

        $reference = 'QRSIM-'.strtoupper(substr(hash('sha256', $request->fundingReference), 0, 24));
        $fundingAddress = 'qrph-simulator:'.$reference;

        return new FundingInstructionsData(
            provider: self::Provider,
            providerReference: $reference,
            amountMinor: $request->amountMinor,
            currency: 'PHP',
            expiresAt: $request->expiresAt ?? new DateTimeImmutable('+30 minutes'),
            fundingAddress: $fundingAddress,
            displayData: [
                'institution' => 'QR Ph Simulator',
                'account_name' => 'Local simulated clearing',
                'destination_account' => $fundingAddress,
                'amount_minor' => $request->amountMinor,
                'currency' => 'PHP',
                'one_time' => true,
                'delivery' => 'local-simulation-only',
            ],
        );
    }

    public function authenticateWebhook(ProviderWebhookRequestData $request): WebhookAuthenticationData
    {
        if (! $this->guard->available()) {
            return new WebhookAuthenticationData(false, 'qrph-simulator-hmac', 'Simulator unavailable.');
        }

        if (strtolower($request->provider) !== self::Provider
            || ! str_starts_with(strtolower((string) $request->contentType), 'application/json')) {
            return new WebhookAuthenticationData(false, 'qrph-simulator-hmac', 'Request shape mismatch.');
        }

        $expected = hash_hmac('sha256', $request->rawBody, $this->signingKey());
        $authenticated = is_string($request->signature)
            && hash_equals($expected, trim($request->signature));

        return new WebhookAuthenticationData(
            authenticated: $authenticated,
            method: 'qrph-simulator-hmac',
            reason: $authenticated ? null : 'Simulator signature mismatch.',
        );
    }

    public function normalizeWebhook(ProviderWebhookReceiptData $receipt): ProviderEventHintData
    {
        $this->guard->assertAvailable();
        $payload = $this->payload($receipt->rawBody);

        return new ProviderEventHintData(
            providerEventId: $this->string($payload, 'event_id'),
            eventType: $this->string($payload, 'event_type'),
            requestId: $this->string($payload, 'request_id'),
        );
    }

    public function verifyFunding(FundingVerificationData $verification): ProviderFundingObservationData
    {
        $this->guard->assertAvailable();
        $this->assertProvider($verification->provider);
        $requestId = trim((string) $verification->providerRequestId);
        $transaction = SimulatedFundingTransaction::query()
            ->where('provider_request_id', $requestId)
            ->first();

        if (! $transaction instanceof SimulatedFundingTransaction) {
            throw new ProviderFundingNotObserved('The simulated QR Ph provider has not observed this payment.');
        }

        $destinationVerified = hash_equals(
            $transaction->funding_address,
            (string) $verification->fundingAddress,
        );

        return new ProviderFundingObservationData(
            provider: self::Provider,
            providerTransactionId: $transaction->provider_transaction_id,
            grossAmountMinor: $transaction->gross_amount_minor,
            feeAmountMinor: $transaction->fee_amount_minor,
            netAmountMinor: $transaction->gross_amount_minor - $transaction->fee_amount_minor,
            currency: $transaction->currency,
            providerStatus: $transaction->status,
            verificationSource: 'qrph-simulated-provider-ledger',
            payloadHash: $transaction->payload_hash,
            requestId: $transaction->provider_request_id,
            fundingAddress: 'sha256:'.hash('sha256', $transaction->funding_address),
            providerAccountReference: 'qrph-simulator-clearing',
            occurredAt: DateTimeImmutable::createFromInterface($transaction->occurred_at),
            settledAt: $transaction->settled_at === null
                ? null
                : DateTimeImmutable::createFromInterface($transaction->settled_at),
            webhookReceiptId: $verification->webhookReceiptId,
            metadata: [
                'destination_verified' => $destinationVerified,
                'simulation' => true,
            ],
            payerIdentity: new ProviderPayerIdentityData(
                mobile: $transaction->payer_mobile_ciphertext,
                verificationSource: 'qrph-simulated-payer-profile',
                providerVerified: true,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $rawBody): array
    {
        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('The QR Ph simulator webhook must be valid JSON.');
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function string(array $payload, string $key): ?string
    {
        $value = data_get($payload, $key);

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function assertProvider(string $provider): void
    {
        if (strtolower(trim($provider)) !== self::Provider) {
            throw new InvalidArgumentException('The QR Ph simulator cannot handle this provider.');
        }
    }

    private function signingKey(): string
    {
        $key = config('x-change.funding.simulator.signing_key') ?: config('app.key');

        if (! is_string($key) || trim($key) === '') {
            throw new LogicException('A QR Ph simulator signing key is required.');
        }

        return $key;
    }
}
