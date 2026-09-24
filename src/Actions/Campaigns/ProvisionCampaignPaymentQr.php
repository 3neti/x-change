<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XCampaign\Models\EndpointCampaign;
use LBHurtado\XChange\Actions\Funding\ProvisionStandingFundingAddress;
use LBHurtado\XChange\Enums\CampaignEntryMode;
use LBHurtado\XChange\Enums\CampaignPaymentAmountMode;
use LBHurtado\XChange\Enums\FundingRecognitionMode;
use LBHurtado\XChange\Models\CampaignPaymentQrBinding;
use LBHurtado\XChange\Services\Funding\FundingQrMerchantProfileResolver;
use LBHurtado\XChange\Services\Funding\StandingFundingDestinationResolver;
use LBHurtado\XChange\Services\Funding\StandingFundingQrArtifactStore;

final readonly class ProvisionCampaignPaymentQr
{
    public function __construct(
        private ProvisionStandingFundingAddress $provision,
        private BindCampaignPaymentQr $bind,
        private StandingFundingDestinationResolver $destinations,
        private FundingQrMerchantProfileResolver $merchantProfiles,
        private StandingFundingQrArtifactStore $qrArtifacts,
    ) {}

    /**
     * @param  array<string, mixed>  $permittedPaymentRules
     */
    public function handle(
        Model $owner,
        EndpointCampaign $campaign,
        CampaignPaymentAmountMode $amountMode,
        ?int $fixedAmountMinor = null,
        ?CarbonImmutable $availableFrom = null,
        ?CarbonImmutable $availableUntil = null,
        array $permittedPaymentRules = [],
    ): CampaignPaymentQrBinding {
        $revisionId = $this->assertProvisionable($owner, $campaign);
        $this->assertPaymentConfiguration(
            $amountMode,
            $fixedAmountMinor,
            $availableFrom,
            $availableUntil,
        );
        $accountReference = implode(':', [
            'campaign',
            $campaign->reference,
            'revision',
            $revisionId,
        ]);
        $destination = $this->destinations->resolve($owner, $accountReference);
        $merchant = $this->merchantProfiles->resolve($owner);
        $provisioned = $this->provision->handle(
            owner: $owner,
            accountReference: $accountReference,
            provider: 'netbank',
            purpose: FundingAddressPurpose::Payment,
            recognitionMode: FundingRecognitionMode::ObserveOnly,
            currency: 'PHP',
            destination: $destination,
            qrMerchant: $merchant,
        );
        $fingerprint = $this->qrArtifacts->fingerprint(
            $provisioned->address,
            $merchant,
        );
        $artifact = $this->qrArtifacts->find(
            $provisioned->address,
            $fingerprint,
        );

        if ($artifact === null) {
            throw ValidationException::withMessages([
                'payment_qr' => 'The provider did not persist an active reusable campaign QR artifact.',
            ]);
        }

        return $this->bind->handle(
            owner: $owner,
            campaign: $campaign,
            address: $provisioned->address,
            artifact: $artifact,
            amountMode: $amountMode,
            fixedAmountMinor: $fixedAmountMinor,
            availableFrom: $availableFrom,
            availableUntil: $availableUntil,
            permittedPaymentRules: $permittedPaymentRules,
        );
    }

    private function assertProvisionable(Model $owner, EndpointCampaign $campaign): string
    {
        $ownerTypes = array_unique([$owner::class, $owner->getMorphClass()]);
        $revisionId = trim((string) $campaign->active_template_version_id);
        $entryMode = CampaignEntryMode::tryFrom((string) data_get(
            $campaign->settings,
            'entry_mode',
        ));

        if (! in_array((string) $campaign->owner_type, $ownerTypes, true)
            || (string) $campaign->owner_id !== (string) $owner->getKey()) {
            throw ValidationException::withMessages([
                'campaign' => 'The campaign must belong to the Account provisioning its payment QR.',
            ]);
        }

        if ($entryMode !== CampaignEntryMode::ReusablePaymentQr || $revisionId === '') {
            throw ValidationException::withMessages([
                'campaign' => 'Choose a reusable-payment-QR campaign with a persisted revision.',
            ]);
        }

        return $revisionId;
    }

    private function assertPaymentConfiguration(
        CampaignPaymentAmountMode $amountMode,
        ?int $fixedAmountMinor,
        ?CarbonImmutable $availableFrom,
        ?CarbonImmutable $availableUntil,
    ): void {
        if (($amountMode === CampaignPaymentAmountMode::Fixed && ($fixedAmountMinor ?? 0) < 1)
            || ($amountMode === CampaignPaymentAmountMode::Open && $fixedAmountMinor !== null)) {
            throw ValidationException::withMessages([
                'amount' => 'The configured campaign payment amount is inconsistent with its amount mode.',
            ]);
        }

        if ($availableFrom instanceof CarbonImmutable
            && $availableUntil instanceof CarbonImmutable
            && $availableUntil->lessThanOrEqualTo($availableFrom)) {
            throw ValidationException::withMessages([
                'availability' => 'Campaign payment availability must end after it starts.',
            ]);
        }
    }
}
