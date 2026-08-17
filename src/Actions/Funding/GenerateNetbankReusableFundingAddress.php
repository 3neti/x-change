<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\PaymentGateway\Enums\NetbankStandingAddressScheme;
use LBHurtado\PaymentGateway\Funding\NetbankStandingAddressProfile;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Data\Funding\NetbankReusableFundingAddressData;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Services\Funding\FundingQrMerchantProfileResolver;
use LBHurtado\XChange\Services\Funding\StandingFundingAccountReferenceResolver;
use LBHurtado\XChange\Services\Funding\StandingFundingDestinationResolver;
use LBHurtado\XChange\Support\Auth\MobileNumber;

final class GenerateNetbankReusableFundingAddress
{
    public function __construct(
        private readonly StandingFundingAccountReferenceResolver $accounts,
        private readonly StandingFundingDestinationResolver $destinations,
        private readonly NetbankStandingAddressProfile $profile,
        private readonly ProvisionStandingFundingAddress $provision,
        private readonly FundingQrMerchantProfileResolver $merchantProfiles,
        private readonly AuditLoggerContract $audit,
    ) {}

    public function handle(Model $owner): NetbankReusableFundingAddressData
    {
        $this->assertEnabled();
        $accountReference = $this->accounts->resolve($owner, 'netbank', 'PHP');
        $mode = FundingRecognitionMode::tryFrom((string) config(
            'x-change.funding.standing_addresses.default_recognition_mode',
            FundingRecognitionMode::ObserveOnly->value,
        )) ?? FundingRecognitionMode::ObserveOnly;
        $provisioned = $this->provision->handle(
            owner: $owner,
            accountReference: $accountReference,
            provider: 'netbank',
            purpose: FundingAddressPurpose::AccountFunding,
            recognitionMode: $mode,
            currency: 'PHP',
            destination: $this->destinations->resolve($owner, $accountReference),
            routingReference: $this->routingReference($owner, $accountReference),
            qrMerchant: $this->merchantProfiles->resolve($owner),
        );
        $address = $provisioned->address;
        $providerAddress = $provisioned->providerAddress;

        $this->audit->log('funding.standing_address.qr_issued', [
            'actor_type' => $owner::class,
            'actor_id' => (string) $owner->getKey(),
            'standing_funding_address_reference' => $address->reference,
            'provider' => 'netbank',
            'purpose' => $address->purpose->value,
            'recognition_mode' => $address->recognition_mode->value,
            'funding_intent_created' => false,
            'automatic_credit_enabled' => $address->recognition_mode === FundingRecognitionMode::Automatic,
        ]);

        return new NetbankReusableFundingAddressData(
            reference: $address->reference,
            provider: $providerAddress->provider,
            fundingAddress: $providerAddress->fundingAddress,
            maskedFundingAddress: '•••• '.Str::substr($providerAddress->fundingAddress, -6),
            purpose: $address->purpose->value,
            recognitionMode: $address->recognition_mode->value,
            status: $address->status->value,
            currency: $providerAddress->currency,
            institution: (string) data_get($providerAddress->displayData, 'institution', 'NetBank'),
            merchantName: (string) data_get($providerAddress->displayData, 'merchant_name', ''),
            qrCode: 'data:'.$providerAddress->qrCode->mimeType.';base64,'.$providerAddress->qrCode->base64Payload,
            qrMode: $providerAddress->qrCode->qrMode,
            transactionType: $providerAddress->qrCode->transactionType,
            embeddedAmount: $providerAddress->qrCode->embeddedAmount,
            providerGenerated: $providerAddress->qrCode->providerGenerated,
            temporary: false,
            fundingIntentCreated: false,
            automaticCreditEnabled: $address->recognition_mode === FundingRecognitionMode::Automatic,
            minimumAmountMinor: $address->minimum_amount_minor,
            maximumAmountMinor: $address->maximum_amount_minor,
            dailyLimitMinor: $address->daily_limit_minor,
        );
    }

    private function routingReference(Model $owner, string $accountReference): ?string
    {
        if ($this->profile->scheme() !== NetbankStandingAddressScheme::MobileV1) {
            return null;
        }

        $existing = StandingFundingAddress::query()
            ->whereMorphedTo('owner', $owner)
            ->where('provider_code', 'netbank')
            ->where('purpose', FundingAddressPurpose::AccountFunding)
            ->where('currency', 'PHP')
            ->exists();

        if ($existing) {
            return null;
        }

        if ($owner->getAttribute('mobile_verified_at') === null) {
            throw ValidationException::withMessages([
                'standing_funding_address' => 'Verify the Account mobile before creating this funding address.',
            ]);
        }

        $mobile = MobileNumber::normalize($owner->getAttribute('mobile'));

        if (! is_string($mobile) || preg_match('/\A639\d{9}\z/', $mobile) !== 1) {
            throw ValidationException::withMessages([
                'standing_funding_address' => 'A valid verified Philippine mobile is required.',
            ]);
        }

        return '0'.substr($mobile, 2);
    }

    private function assertEnabled(): void
    {
        if (! (bool) config('x-change.funding.standing_addresses.enabled', false)) {
            throw ValidationException::withMessages([
                'standing_funding_address' => 'Standing Funding Addresses are disabled.',
            ]);
        }
    }
}
