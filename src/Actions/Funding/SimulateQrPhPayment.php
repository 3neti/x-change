<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LBHurtado\XChange\Data\Funding\SimulatedQrPhPaymentData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\PaymentAttempt;
use LBHurtado\XChange\Models\SimulatedFundingTransaction;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use LBHurtado\XChange\Support\Funding\QrPhFundingSimulatorGuard;
use LogicException;

class SimulateQrPhPayment
{
    public function __construct(
        private readonly QrPhFundingSimulatorGuard $guard,
    ) {}

    public function handle(FundingIntent $intent, string $payerMobile, ?string $payerName = null): SimulatedQrPhPaymentData
    {
        $this->guard->assertAvailable();
        $intent = FundingIntent::query()->findOrFail($intent->getKey());
        $mobile = MobileNumber::normalize($payerMobile);

        if ($intent->provider_code !== 'qrph_simulator'
            || $intent->status !== FundingIntentStatus::AwaitingFunds
            || blank($intent->provider_request_id)
            || blank($intent->funding_address_ciphertext)) {
            throw new InvalidArgumentException('A ready QR Ph simulator Funding Intent is required.');
        }

        if ($mobile === null) {
            throw new InvalidArgumentException('A valid payer mobile number is required.');
        }

        return $this->record($intent->provider_request_id, $intent->funding_address_ciphertext, $intent->expected_amount_minor, $intent->currency, $mobile, $payerName);
    }

    public function handlePaymentAttempt(PaymentAttempt $attempt, string $payerMobile, ?string $payerName = null, ?string $paymentKey = null, ?int $amountMinor = null): SimulatedQrPhPaymentData
    {
        $this->guard->assertAvailable();
        $attempt = PaymentAttempt::query()->findOrFail($attempt->getKey());
        $mobile = MobileNumber::normalize($payerMobile);

        if ($attempt->provider_code !== 'qrph_simulator'
            || (! in_array($attempt->status, [PaymentAttemptStatus::AwaitingPayment, PaymentAttemptStatus::Settled], true))
            || ($attempt->status === PaymentAttemptStatus::Settled && blank($paymentKey))
            || blank($attempt->provider_request_id_ciphertext)
            || blank($attempt->funding_address_ciphertext)) {
            throw new InvalidArgumentException('A ready QR Ph simulator Payment Attempt is required.');
        }
        if ($mobile === null) {
            throw new InvalidArgumentException('A valid payer mobile number is required.');
        }
        if ($amountMinor !== null && $amountMinor <= 0) {
            throw new InvalidArgumentException('A simulated payment amount must be positive.');
        }

        return $this->record($attempt->provider_request_id_ciphertext, $attempt->funding_address_ciphertext, $amountMinor ?? $attempt->expected_amount_minor, $attempt->currency, $mobile, $payerName, $paymentKey);
    }

    private function record(string $requestId, string $fundingAddress, int $amountMinor, string $currency, string $mobile, ?string $payerName, ?string $paymentKey = null): SimulatedQrPhPaymentData
    {
        $name = trim((string) $payerName);
        if ($paymentKey !== null && (trim($paymentKey) === '' || mb_strlen($paymentKey) > 191)) {
            throw new InvalidArgumentException('A valid simulation payment key is required.');
        }
        $key = hash('sha256', $requestId."\0".($paymentKey === null ? 'single' : 'payment:'.$paymentKey));

        $attributes = [
            'provider_request_id' => $requestId,
            'simulation_key_hash' => $key,
            'provider_transaction_id' => 'QRSIM-TXN-'.Str::upper((string) Str::ulid()),
            'provider_event_id' => 'QRSIM-EVT-'.Str::upper((string) Str::ulid()),
            'funding_address' => $fundingAddress,
            'payer_mobile_ciphertext' => $mobile,
            'payer_mobile_hash' => $this->mobileHash($mobile),
            'payer_name_ciphertext' => $name !== '' ? $name : null,
            'gross_amount_minor' => $amountMinor,
            'fee_amount_minor' => 0,
            'currency' => $currency,
            'status' => 'settled',
            'occurred_at' => now(),
            'settled_at' => now(),
        ];
        $payloadHash = $this->payloadHash($attributes);

        $transaction = $paymentKey === null ? SimulatedFundingTransaction::query()
            ->where('provider_request_id', $requestId)->whereNull('simulation_key_hash')->first() : null;
        try {
            if ($transaction !== null) {
                if (! hash_equals($transaction->payload_hash, $payloadHash)) {
                    throw new InvalidArgumentException('This simulator payment key has different payment details.');
                }
            } else {
                $transaction = SimulatedFundingTransaction::query()->create([
                    ...$attributes,
                    'payload_hash' => $payloadHash,
                ]);
            }
        } catch (UniqueConstraintViolationException $exception) {
            $transaction = SimulatedFundingTransaction::query()
                ->where('simulation_key_hash', $key)
                ->first();

            if (! $transaction instanceof SimulatedFundingTransaction
                || ! hash_equals($transaction->payload_hash, $payloadHash)) {
                throw $exception;
            }
        }

        $rawBody = json_encode([
            'event_id' => $transaction->provider_event_id,
            'event_type' => 'qrph.payment.settled',
            'request_id' => $transaction->provider_request_id,
            'transaction_id' => $transaction->provider_transaction_id,
            'status' => $transaction->status,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return new SimulatedQrPhPaymentData(
            transaction: $transaction,
            rawBody: $rawBody,
            signature: hash_hmac('sha256', $rawBody, $this->signingKey()),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function payloadHash(array $attributes): string
    {
        return hash('sha256', json_encode([
            'provider_request_id' => $attributes['provider_request_id'],
            'funding_address' => $attributes['funding_address'],
            'payer_mobile_hash' => $attributes['payer_mobile_hash'],
            'payer_name_ciphertext' => $attributes['payer_name_ciphertext'],
            'gross_amount_minor' => $attributes['gross_amount_minor'],
            'fee_amount_minor' => $attributes['fee_amount_minor'],
            'currency' => $attributes['currency'],
            'status' => $attributes['status'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function mobileHash(string $mobile): string
    {
        $key = config('x-change.funding.simulator.mobile_hash_key') ?: config('app.key');

        if (! is_string($key) || trim($key) === '') {
            throw new LogicException('A QR Ph simulator mobile hash key is required.');
        }

        return hash_hmac('sha256', $mobile, $key);
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
