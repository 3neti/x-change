<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\EmiCore\Enums\FundingAddressPurpose;
use LBHurtado\XCampaign\Models\EndpointCampaign;
use LBHurtado\XChange\Enums\CampaignEntryMode;
use LBHurtado\XChange\Enums\CampaignPaymentAmountMode;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Models\CampaignPaymentQrBinding;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingQrArtifact;

final readonly class BindCampaignPaymentQr
{
    /**
     * @param  array<string, mixed>  $permittedPaymentRules
     */
    public function handle(
        Model $owner,
        EndpointCampaign $campaign,
        StandingFundingAddress $address,
        StandingFundingQrArtifact $artifact,
        CampaignPaymentAmountMode $amountMode,
        ?int $fixedAmountMinor = null,
        ?CarbonImmutable $availableFrom = null,
        ?CarbonImmutable $availableUntil = null,
        array $permittedPaymentRules = [],
    ): CampaignPaymentQrBinding {
        $revisionId = trim((string) $campaign->active_template_version_id);
        $entryMode = CampaignEntryMode::tryFrom((string) data_get(
            $campaign->settings,
            'entry_mode',
        ));

        $this->validateConfiguration(
            owner: $owner,
            campaign: $campaign,
            address: $address,
            artifact: $artifact,
            entryMode: $entryMode,
            revisionId: $revisionId,
            amountMode: $amountMode,
            fixedAmountMinor: $fixedAmountMinor,
            availableFrom: $availableFrom,
            availableUntil: $availableUntil,
        );

        $rules = $this->canonicalize($permittedPaymentRules);
        $configurationHash = hash('sha256', json_encode([
            'campaign_reference' => $campaign->reference,
            'campaign_revision_id' => $revisionId,
            'standing_funding_address_reference' => $address->reference,
            'qr_artifact_reference' => $artifact->reference,
            'entry_mode' => CampaignEntryMode::ReusablePaymentQr->value,
            'provider_code' => $address->provider_code,
            'currency' => strtoupper((string) $address->currency),
            'amount_mode' => $amountMode->value,
            'fixed_amount_minor' => $fixedAmountMinor,
            'available_from' => $availableFrom?->toIso8601String(),
            'available_until' => $availableUntil?->toIso8601String(),
            'permitted_payment_rules' => $rules,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use (
            $campaign,
            $address,
            $artifact,
            $revisionId,
            $amountMode,
            $fixedAmountMinor,
            $availableFrom,
            $availableUntil,
            $rules,
            $configurationHash,
        ): CampaignPaymentQrBinding {
            $lockedCampaign = $campaign->newQuery()->lockForUpdate()->findOrFail($campaign->getKey());
            $lockedAddress = $address->newQuery()->lockForUpdate()->findOrFail($address->getKey());
            $lockedArtifact = $artifact->newQuery()->lockForUpdate()->findOrFail($artifact->getKey());

            if ((string) $lockedCampaign->active_template_version_id !== $revisionId
                || CampaignEntryMode::tryFrom((string) data_get(
                    $lockedCampaign->settings,
                    'entry_mode',
                )) !== CampaignEntryMode::ReusablePaymentQr
                || $lockedAddress->status !== FundingAddressStatus::Active
                || (int) $lockedArtifact->standing_funding_address_id !== (int) $lockedAddress->getKey()
                || $lockedArtifact->status !== 'active') {
                throw ValidationException::withMessages([
                    'campaign' => 'The campaign revision or payment address changed before binding.',
                ]);
            }

            $existing = CampaignPaymentQrBinding::query()
                ->where('endpoint_campaign_id', $lockedCampaign->getKey())
                ->where('campaign_revision_id', $revisionId)
                ->first();

            if ($existing instanceof CampaignPaymentQrBinding) {
                if (! hash_equals($existing->configuration_hash, $configurationHash)) {
                    throw ValidationException::withMessages([
                        'campaign' => 'This campaign revision is already bound to different payment QR configuration.',
                    ]);
                }

                return $existing;
            }

            return CampaignPaymentQrBinding::query()->create([
                'endpoint_campaign_id' => $lockedCampaign->getKey(),
                'campaign_revision_id' => $revisionId,
                'standing_funding_address_id' => $lockedAddress->getKey(),
                'standing_funding_qr_artifact_id' => $lockedArtifact->getKey(),
                'entry_mode' => CampaignEntryMode::ReusablePaymentQr,
                'provider_code' => strtolower((string) $lockedAddress->provider_code),
                'currency' => strtoupper((string) $lockedAddress->currency),
                'amount_mode' => $amountMode,
                'fixed_amount_minor' => $fixedAmountMinor,
                'available_from' => $availableFrom,
                'available_until' => $availableUntil,
                'permitted_payment_rules' => $rules,
                'configuration_hash' => $configurationHash,
            ]);
        }, attempts: 3);
    }

    private function validateConfiguration(
        Model $owner,
        EndpointCampaign $campaign,
        StandingFundingAddress $address,
        StandingFundingQrArtifact $artifact,
        ?CampaignEntryMode $entryMode,
        string $revisionId,
        CampaignPaymentAmountMode $amountMode,
        ?int $fixedAmountMinor,
        ?CarbonImmutable $availableFrom,
        ?CarbonImmutable $availableUntil,
    ): void {
        $ownerTypes = array_unique([$owner::class, $owner->getMorphClass()]);
        $ownerId = (string) $owner->getKey();

        if (! in_array((string) $campaign->owner_type, $ownerTypes, true)
            || (string) $campaign->owner_id !== $ownerId
            || ! in_array((string) $address->owner_type, $ownerTypes, true)
            || (string) $address->owner_id !== $ownerId) {
            throw ValidationException::withMessages([
                'campaign' => 'The campaign and payment address must belong to the same account.',
            ]);
        }

        if ($entryMode !== CampaignEntryMode::ReusablePaymentQr || $revisionId === '') {
            throw ValidationException::withMessages([
                'campaign' => 'Choose a reusable-payment-QR campaign with a persisted revision.',
            ]);
        }

        if ($address->purpose !== FundingAddressPurpose::Payment
            || $address->status !== FundingAddressStatus::Active
            || ! $artifact->standingFundingAddress->is($address)
            || $artifact->status !== 'active'
            || ! in_array(strtolower((string) $artifact->qr_mode), ['static', 'reusable'], true)) {
            throw ValidationException::withMessages([
                'payment_qr' => 'An active reusable payment-purpose QR is required.',
            ]);
        }

        if (($amountMode === CampaignPaymentAmountMode::Fixed && ($fixedAmountMinor ?? 0) < 1)
            || ($amountMode === CampaignPaymentAmountMode::Open && $fixedAmountMinor !== null)) {
            throw ValidationException::withMessages([
                'amount' => 'The configured campaign payment amount is inconsistent with its amount mode.',
            ]);
        }

        if ($amountMode === CampaignPaymentAmountMode::Fixed
            && (($address->minimum_amount_minor !== null
                    && $fixedAmountMinor < (int) $address->minimum_amount_minor)
                || ($address->maximum_amount_minor !== null
                    && $fixedAmountMinor > (int) $address->maximum_amount_minor))) {
            throw ValidationException::withMessages([
                'amount' => 'The fixed campaign payment amount is outside the provider address limits.',
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

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function canonicalize(array $value): array
    {
        ksort($value);

        return array_map(
            fn (mixed $item): mixed => is_array($item) ? $this->canonicalize($item) : $item,
            $value,
        );
    }
}
