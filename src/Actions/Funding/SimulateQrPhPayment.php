<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LBHurtado\XChange\Data\Funding\SimulatedQrPhPaymentData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\SimulatedFundingTransaction;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use LBHurtado\XChange\Support\Funding\QrPhFundingSimulatorGuard;
use LogicException;

class SimulateQrPhPayment
{
    public function __construct(
        private readonly QrPhFundingSimulatorGuard $guard,
    ) {}

    public function handle(FundingIntent $intent, string $payerMobile): SimulatedQrPhPaymentData
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

        $attributes = [
            'provider_request_id' => $intent->provider_request_id,
            'provider_transaction_id' => 'QRSIM-TXN-'.Str::upper((string) Str::ulid()),
            'provider_event_id' => 'QRSIM-EVT-'.Str::upper((string) Str::ulid()),
            'funding_address' => $intent->funding_address_ciphertext,
            'payer_mobile_ciphertext' => $mobile,
            'payer_mobile_hash' => $this->mobileHash($mobile),
            'gross_amount_minor' => $intent->expected_amount_minor,
            'fee_amount_minor' => 0,
            'currency' => $intent->currency,
            'status' => 'settled',
            'occurred_at' => now(),
            'settled_at' => now(),
        ];
        $payloadHash = $this->payloadHash($attributes);

        try {
            $transaction = SimulatedFundingTransaction::query()->create([
                ...$attributes,
                'payload_hash' => $payloadHash,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $transaction = SimulatedFundingTransaction::query()
                ->where('provider_request_id', $intent->provider_request_id)
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
