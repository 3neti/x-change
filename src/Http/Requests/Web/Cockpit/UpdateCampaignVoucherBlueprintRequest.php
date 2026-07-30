<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LBHurtado\Voucher\Data\RiderStampData;
use LBHurtado\Voucher\Enums\RiderContentFormat;
use LBHurtado\Voucher\Enums\RiderStampArtworkSource;
use LBHurtado\Voucher\Enums\RiderStampArtworkTreatment;
use LBHurtado\Voucher\Enums\RiderStampClaimMarker;
use LBHurtado\Voucher\Enums\RiderStampClaimMarkerPosition;
use LBHurtado\Voucher\Enums\RiderStampCopySource;
use LBHurtado\Voucher\Enums\RiderStampFit;
use LBHurtado\Voucher\Enums\RiderStampPosition;
use LBHurtado\Voucher\Enums\RiderStampSource;
use LBHurtado\Voucher\Enums\RiderStampTheme;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\XCampaign\Models\CampaignWorksheet;

class UpdateCampaignVoucherBlueprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        $owner = $this->user();
        $reference = (string) $this->route('worksheet');

        return $owner !== null && CampaignWorksheet::query()
            ->where('reference', $reference)
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (string) $owner->getAuthIdentifier())
            ->where('status', 'draft')
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expected_revision' => ['required', 'integer', 'min:0'],
            'blueprint' => ['required', 'array'],
            'blueprint.onboarding' => ['nullable', 'boolean'],
            'blueprint.expiry_days' => ['required', 'integer', 'between:1,365'],
            'blueprint.inputs' => ['required', 'array'],
            'blueprint.inputs.fields' => ['present', 'array', 'max:12'],
            'blueprint.inputs.fields.*' => ['string', Rule::enum(VoucherInputField::class), 'distinct:strict'],
            'blueprint.feedback' => ['required', 'array'],
            'blueprint.feedback.channels' => ['present', 'array', 'max:2'],
            'blueprint.feedback.channels.*' => ['string', Rule::in(['email', 'mobile']), 'distinct:strict'],
            'blueprint.rider' => ['required', 'array'],
            'blueprint.rider.message' => ['nullable', 'string', 'max:5000'],
            'blueprint.rider.url' => ['nullable', 'url', 'max:2048'],
            'blueprint.rider.redirect_timeout' => ['nullable', 'integer', 'between:0,300'],
            'blueprint.rider.splash' => ['nullable', 'string', 'max:51200'],
            'blueprint.rider.splash_timeout' => ['nullable', 'integer', 'between:0,60'],
            'blueprint.rider.splash_meta' => ['nullable', 'array'],
            'blueprint.rider.og_source' => ['nullable', Rule::in(['message', 'url', 'splash'])],
            'blueprint.rider.message_format' => ['nullable', Rule::enum(RiderContentFormat::class)],
            'blueprint.rider.splash_format' => ['nullable', Rule::enum(RiderContentFormat::class)],
            'blueprint.rider.stamp' => ['nullable', 'array'],
            'blueprint.rider.stamp.source' => ['nullable', Rule::enum(RiderStampSource::class)],
            'blueprint.rider.stamp.title' => ['nullable', 'string', 'max:120'],
            'blueprint.rider.stamp.description' => ['nullable', 'string', 'max:240'],
            'blueprint.rider.stamp.fit' => ['nullable', Rule::enum(RiderStampFit::class)],
            'blueprint.rider.stamp.position' => ['nullable', Rule::enum(RiderStampPosition::class)],
            'blueprint.rider.stamp.scrim' => ['nullable', 'integer', 'between:0,100'],
            'blueprint.rider.stamp.theme' => ['nullable', Rule::enum(RiderStampTheme::class)],
            'blueprint.rider.stamp.version' => ['nullable', 'integer', Rule::in([
                RiderStampData::LEGACY_SCHEMA_VERSION,
                RiderStampData::SCHEMA_VERSION,
            ])],
            'blueprint.rider.stamp.artwork_source' => ['nullable', Rule::enum(RiderStampArtworkSource::class)],
            'blueprint.rider.stamp.artwork_treatment' => ['nullable', Rule::enum(RiderStampArtworkTreatment::class)],
            'blueprint.rider.stamp.copy_source' => ['nullable', Rule::enum(RiderStampCopySource::class)],
            'blueprint.rider.stamp.show_logo' => ['nullable', 'boolean'],
            'blueprint.rider.stamp.show_tagline' => ['nullable', 'boolean'],
            'blueprint.rider.stamp.claim_marker' => ['nullable', Rule::enum(RiderStampClaimMarker::class)],
            'blueprint.rider.stamp.claim_marker_position' => ['nullable', Rule::enum(RiderStampClaimMarkerPosition::class)],
            'blueprint.validation' => ['nullable', 'array'],
            'blueprint.validation.otp' => ['nullable', 'array'],
            'blueprint.validation.otp.required' => ['required_with:blueprint.validation.otp', 'boolean'],
            'blueprint.validation.otp.on_failure' => ['required_with:blueprint.validation.otp', Rule::in(['block', 'warn'])],
            'blueprint.validation.selfie' => ['nullable', 'array'],
            'blueprint.validation.selfie.required' => ['required_with:blueprint.validation.selfie', 'boolean'],
            'blueprint.validation.selfie.on_failure' => ['required_with:blueprint.validation.selfie', Rule::in(['block', 'warn'])],
            'blueprint.validation.signature' => ['nullable', 'array'],
            'blueprint.validation.signature.required' => ['required_with:blueprint.validation.signature', 'boolean'],
            'blueprint.validation.signature.on_failure' => ['required_with:blueprint.validation.signature', Rule::in(['block', 'warn'])],
            'blueprint.claim' => ['nullable', 'array'],
            'blueprint.claim.onboarding' => ['nullable', 'array'],
            'blueprint.claim.onboarding.mode' => ['nullable', Rule::in(['never', 'if_required', 'required'])],
        ];
    }
}
