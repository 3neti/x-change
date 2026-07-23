<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use LogicException;

class MatchFundingPayerIdentity
{
    public function handle(
        FundingIntent $intent,
        ProviderFundingObservationData $observation,
    ): ProviderFundingObservationData {
        $identity = $observation->payerIdentity;

        if ($identity === null) {
            return $observation;
        }

        $payerMobile = MobileNumber::normalize($identity->mobile);
        $owner = $this->owner($intent);
        $ownerMobile = $owner === null ? null : $this->ownerMobile($owner);
        $ownerVerified = $owner !== null && $this->ownerMobileIsVerified($owner);
        $matched = $identity->providerVerified
            && $ownerVerified
            && $payerMobile !== null
            && $ownerMobile !== null
            && hash_equals($ownerMobile, $payerMobile);

        return new ProviderFundingObservationData(
            provider: $observation->provider,
            providerTransactionId: $observation->providerTransactionId,
            grossAmountMinor: $observation->grossAmountMinor,
            feeAmountMinor: $observation->feeAmountMinor,
            netAmountMinor: $observation->netAmountMinor,
            currency: $observation->currency,
            providerStatus: $observation->providerStatus,
            verificationSource: $observation->verificationSource,
            payloadHash: $observation->payloadHash,
            providerOperationId: $observation->providerOperationId,
            requestId: $observation->requestId,
            fundingAddress: $observation->fundingAddress,
            providerAccountReference: $observation->providerAccountReference,
            occurredAt: $observation->occurredAt,
            settledAt: $observation->settledAt,
            webhookReceiptId: $observation->webhookReceiptId,
            metadata: [
                ...$observation->metadata,
                'payer_identity_required' => true,
                'payer_identity_matched' => $matched,
                'payer_mobile_hash' => $payerMobile === null ? null : $this->mobileHash($payerMobile),
                'payer_mobile_masked' => $payerMobile === null ? null : $this->maskedMobile($payerMobile),
                'payer_identity_verification_source' => $identity->verificationSource,
                'payer_identity_provider_verified' => $identity->providerVerified,
                'payer_owner_mobile_verified' => $ownerVerified,
            ],
        );
    }

    private function owner(FundingIntent $intent): ?Model
    {
        $class = $intent->created_by_type;

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            return null;
        }

        return $class::query()->find($intent->created_by_id);
    }

    private function ownerMobile(Model $owner): ?string
    {
        $mobile = $owner->getRawOriginal('mobile');

        if (! is_string($mobile) && $owner instanceof HasMobileChannel) {
            $mobile = $owner->getMobileChannel();
        }

        return MobileNumber::normalize(is_string($mobile) ? $mobile : null);
    }

    private function ownerMobileIsVerified(Model $owner): bool
    {
        return $owner->getAttribute('mobile_verified_at') !== null;
    }

    private function mobileHash(string $mobile): string
    {
        $key = config('x-change.funding.payer_identity_hash_key') ?: config('app.key');

        if (! is_string($key) || trim($key) === '') {
            throw new LogicException('A Funding payer identity hash key is required.');
        }

        return hash_hmac('sha256', $mobile, $key);
    }

    private function maskedMobile(string $mobile): string
    {
        return Str::mask($mobile, '•', 2, max(0, strlen($mobile) - 6));
    }
}
