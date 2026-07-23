<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\PaymentGateway\Funding\NetbankReusableFundingAddressProvider;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Data\Funding\NetbankReusableFundingAddressData;

final class GenerateNetbankReusableFundingAddress
{
    public function __construct(
        private readonly NetbankReusableFundingAddressProvider $netbank,
        private readonly AuditLoggerContract $audit,
    ) {}

    public function handle(Model $owner): NetbankReusableFundingAddressData
    {
        $this->assertEnabled();
        $address = $this->netbank->create($this->ownerReference($owner));

        $this->audit->log('funding.reusable_address.generated', [
            'actor_type' => $owner::class,
            'actor_id' => (string) $owner->getKey(),
            'provider' => 'netbank',
            'mode' => 'temporary-reusable-static-qr',
            'funding_intent_created' => false,
            'automatic_credit_enabled' => false,
        ]);

        return new NetbankReusableFundingAddressData(
            provider: $address->provider,
            fundingAddress: $address->fundingAddress,
            maskedFundingAddress: '•••• '.Str::substr($address->fundingAddress, -6),
            currency: $address->currency,
            institution: $address->institution,
            merchantName: $address->merchantName,
            qrCode: 'data:'.$address->qrCode->mimeType.';base64,'.$address->qrCode->base64Payload,
            qrMode: $address->qrCode->qrMode,
            transactionType: $address->qrCode->transactionType,
            embeddedAmount: $address->qrCode->embeddedAmount,
            providerGenerated: $address->qrCode->providerGenerated,
            temporary: true,
            fundingIntentCreated: false,
            automaticCreditEnabled: false,
        );
    }

    private function ownerReference(Model $owner): string
    {
        return $owner::class.':'.$owner->getKey();
    }

    private function assertEnabled(): void
    {
        if (! (bool) config('x-change.funding.reusable_address.enabled', false)) {
            throw ValidationException::withMessages([
                'reusable_address' => 'The temporary reusable funding address is disabled.',
            ]);
        }
    }
}
