<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Support\Funding\FundingDestinationSnapshot;
use LogicException;
use RuntimeException;
use Throwable;

class IssuePaymentInstructions
{
    public function __construct(
        private readonly FundingProviderAdapterRegistry $providers,
        private readonly FundingDestinationResolverContract $destinations,
    ) {}

    public function handle(PaymentAttempt $attempt): PaymentAttempt
    {
        return Cache::lock(
            'x-change:payment-instructions:'.$attempt->getKey(),
            (int) config('x-change.payment.attempts.instruction_lock_seconds', 30),
        )->block(
            (int) config('x-change.payment.attempts.instruction_lock_wait_seconds', 5),
            fn (): PaymentAttempt => $this->issue($attempt),
        );
    }

    private function issue(PaymentAttempt $attempt): PaymentAttempt
    {
        $current = PaymentAttempt::query()->with('voucher')->findOrFail($attempt->getKey());

        if ($current->status === PaymentAttemptStatus::AwaitingPayment) {
            return $current->load('events');
        }

        if ($current->status !== PaymentAttemptStatus::PendingInstructions) {
            throw new LogicException('Payment instructions cannot be issued from the current state.');
        }

        try {
            $destination = $this->destinations->shared(
                $current->provider_code,
                'voucher:'.$current->voucher_id,
            );

            $instructions = $this->providers
                ->for($current->provider_code)
                ->createFundingInstructions(new FundingInstructionRequestData(
                    provider: $current->provider_code,
                    fundingReference: $current->reference,
                    amountMinor: $current->expected_amount_minor,
                    currency: $current->currency,
                    accountReference: 'voucher:'.$current->voucher_id,
                    expiresAt: $current->expires_at === null
                        ? null
                        : DateTimeImmutable::createFromInterface($current->expires_at),
                    metadata: [
                        'purpose' => 'voucher_payment',
                        'payment_attempt_reference' => $current->reference,
                        'voucher_code' => (string) $current->voucher->code,
                    ],
                    destination: $destination,
                ));

            $this->assertInstructionsMatch($current, $instructions);
        } catch (Throwable $exception) {
            $this->recordInstructionFailure($current);

            throw new RuntimeException(
                'Payment instructions are temporarily unavailable.',
                previous: $exception,
            );
        }

        return DB::transaction(function () use ($current, $instructions, $destination): PaymentAttempt {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($current->getKey());

            if ($locked->status === PaymentAttemptStatus::AwaitingPayment) {
                return $locked->load('events');
            }

            if ($locked->status !== PaymentAttemptStatus::PendingInstructions) {
                throw new LogicException('Payment instructions cannot be issued from the current state.');
            }

            $nextVersion = $locked->version + 1;

            $locked->forceFill([
                'status' => PaymentAttemptStatus::AwaitingPayment,
                'version' => $nextVersion,
                'provider_reference_hash' => $this->secureHash($instructions->providerReference),
                'provider_request_id_ciphertext' => $instructions->providerReference,
                'funding_address_ciphertext' => $instructions->fundingAddress,
                'funding_address_hash' => $this->secureHash((string) $instructions->fundingAddress),
                'instructions_ciphertext' => $this->instructionPayload($instructions),
                'destination_snapshot_ciphertext' => FundingDestinationSnapshot::fromData($destination),
                'destination_fingerprint' => $destination->fingerprint,
                'instructions_created_at' => now(),
                'expires_at' => $instructions->expiresAt ?? $locked->expires_at,
            ])->saveQuietly();

            $locked->events()->create([
                'sequence' => $nextVersion,
                'event_type' => 'provider_instructions_created',
                'from_status' => PaymentAttemptStatus::PendingInstructions,
                'to_status' => PaymentAttemptStatus::AwaitingPayment,
                'trigger' => 'system',
                'metadata' => [
                    'provider' => $instructions->provider,
                    'expires_at' => $instructions->expiresAt?->format(DATE_ATOM),
                ],
                'occurred_at' => now(),
            ]);

            return $locked->refresh()->load('events');
        }, 3);
    }

    private function assertInstructionsMatch(
        PaymentAttempt $attempt,
        FundingInstructionsData $instructions,
    ): void {
        if (strtolower(trim($instructions->provider)) !== $attempt->provider_code
            || $instructions->amountMinor !== $attempt->expected_amount_minor
            || strtoupper(trim($instructions->currency)) !== $attempt->currency) {
            throw new InvalidArgumentException('Provider instructions do not match the Payment Attempt.');
        }

        if (trim($instructions->providerReference) === ''
            || trim((string) $instructions->fundingAddress) === ''
            || $instructions->qrCode === null) {
            throw new InvalidArgumentException('Payment instructions require a reference, destination, and QR code.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function instructionPayload(FundingInstructionsData $instructions): array
    {
        return [
            'provider' => $instructions->provider,
            'amount_minor' => $instructions->amountMinor,
            'currency' => $instructions->currency,
            'expires_at' => $instructions->expiresAt?->format(DATE_ATOM),
            'display_data' => $instructions->displayData,
            'qr_code' => [
                'mime_type' => $instructions->qrCode?->mimeType,
                'base64_payload' => $instructions->qrCode?->base64Payload,
                'qr_mode' => $instructions->qrCode?->qrMode,
                'transaction_type' => $instructions->qrCode?->transactionType,
                'embedded_amount' => $instructions->qrCode?->embeddedAmount,
                'provider_generated' => $instructions->qrCode?->providerGenerated,
            ],
        ];
    }

    private function secureHash(string $value): string
    {
        $key = config('x-change.payment.attempts.hash_key') ?: config('app.key');

        if (! is_string($key) || trim($key) === '') {
            throw new LogicException('A Payment Attempt hash key must be configured.');
        }

        return hash_hmac('sha256', $value, $key);
    }

    private function recordInstructionFailure(PaymentAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt): void {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());

            if ($locked->status !== PaymentAttemptStatus::PendingInstructions) {
                return;
            }

            $nextVersion = $locked->version + 1;

            $locked->forceFill([
                'version' => $nextVersion,
            ])->saveQuietly();

            $locked->events()->create([
                'sequence' => $nextVersion,
                'event_type' => 'provider_instruction_failed',
                'from_status' => PaymentAttemptStatus::PendingInstructions,
                'to_status' => PaymentAttemptStatus::PendingInstructions,
                'trigger' => 'system',
                'metadata' => [
                    'provider' => $locked->provider_code,
                    'retryable' => true,
                ],
                'occurred_at' => now(),
            ]);
        }, 3);
    }
}
