<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LBHurtado\EmiCore\Enums\SettlementRail;
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
use LBHurtado\XChange\Enums\CockpitPayeeKind;
use LBHurtado\XChange\Http\Requests\Concerns\SanitizesRiderSplashHtml;
use LBHurtado\XChange\Http\Requests\Concerns\ValidatesCockpitPayeePolicy;
use LBHurtado\XChange\Http\Requests\Concerns\ValidatesMinimumWithdrawalPolicy;
use LBHurtado\XChange\Services\RiderStampDesignRegistry;
use Propaganistas\LaravelPhone\Rules\Phone;

class GeneratePayCodeRequest extends FormRequest
{
    use SanitizesRiderSplashHtml;
    use ValidatesCockpitPayeePolicy;
    use ValidatesMinimumWithdrawalPolicy {
        after as minimumWithdrawalPolicyValidators;
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            ...$this->minimumWithdrawalPolicyValidators(),
            ...$this->cockpitPayeePolicyValidators(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cash' => ['required', 'array'],
            'cash.amount' => ['required', 'numeric', 'min:0.01'],
            'cash.currency' => ['required', 'string', 'max:10'],
            'cash.settlement_rail' => ['nullable', Rule::enum(SettlementRail::class)],
            'cash.slice_mode' => ['nullable', 'string', 'in:fixed,open'],
            'cash.slices' => ['nullable', 'integer', 'min:1'],
            'cash.max_slices' => ['nullable', 'integer', 'min:1'],
            'cash.min_withdrawal' => ['nullable', 'numeric', 'min:0'],
            'cash.fee_strategy' => ['nullable', 'string', 'max:80'],
            'cash.type' => ['nullable', 'string', 'max:80'],
            'cash.mandates' => ['nullable', 'array'],
            'cash.mandates.*' => ['string', 'max:120'],

            'cash.validation' => ['nullable', 'array'],
            'cash.validation.secret' => ['nullable', 'string', 'min:4', 'max:255'],
            'cash.validation.mobile' => ['nullable', 'string'],
            'cash.validation.mobile_verification' => ['nullable', 'array:driver,enforcement'],
            'cash.validation.mobile_verification.driver' => ['nullable', 'string', 'max:80'],
            'cash.validation.mobile_verification.enforcement' => ['nullable', 'string', 'in:strict,soft'],
            'cash.validation.payable' => ['nullable', 'string'],
            'cash.validation.country' => ['nullable', 'string', 'max:10'],
            'cash.validation.location' => ['nullable', 'string'],
            'cash.validation.radius' => ['nullable', 'string'],

            'inputs' => ['required', 'array'],
            'inputs.fields' => ['present', 'array'],
            'inputs.fields.*' => ['string', 'max:120', 'distinct:strict'],
            'inputs.requirements' => ['nullable', 'array'],
            'inputs.requirements.*' => ['string', 'max:120'],

            'feedback' => ['required', 'array'],
            'feedback.email' => ['nullable', 'email'],
            'feedback.mobile' => ['nullable', (new Phone)->country('PH')->type('mobile')],
            'feedback.webhook' => ['nullable', 'url:http,https'],

            'rider' => ['required', 'array'],
            'rider.message' => ['nullable'],
            'rider.url' => ['nullable', 'url'],
            'rider.redirect_timeout' => ['nullable'],
            'rider.splash' => ['nullable'],
            'rider.splash_timeout' => ['nullable'],
            'rider.splash_meta' => ['nullable', 'array'],
            'rider.splash_meta.sanitized' => ['nullable', 'boolean'],
            'rider.splash_meta.html_profile' => ['nullable', 'string'],
            'rider.og_source' => ['nullable'],
            'rider.message_format' => ['nullable', Rule::enum(RiderContentFormat::class)],
            'rider.splash_format' => ['nullable', Rule::enum(RiderContentFormat::class)],
            'rider.stamp' => ['nullable', 'array'],
            'rider.stamp.source' => ['nullable', Rule::enum(RiderStampSource::class)],
            'rider.stamp.title' => ['nullable', 'string', 'max:120'],
            'rider.stamp.description' => ['nullable', 'string', 'max:240'],
            'rider.stamp.fit' => ['nullable', Rule::enum(RiderStampFit::class)],
            'rider.stamp.position' => ['nullable', Rule::enum(RiderStampPosition::class)],
            'rider.stamp.scrim' => ['nullable', 'integer', 'between:0,100'],
            'rider.stamp.theme' => ['nullable', Rule::enum(RiderStampTheme::class)],
            'rider.stamp.version' => ['nullable', 'integer', Rule::in(
                RiderStampDesignRegistry::supportedStampSchemaVersions(),
            )],
            'rider.stamp.design_id' => [
                'required_if:rider.stamp.version,'.RiderStampDesignRegistry::StampSchemaVersion,
                'nullable',
                'string',
                Rule::in(array_keys((array) config('x-change.experience.stamp_designs', []))),
            ],
            'rider.stamp.design_version' => [
                'required_if:rider.stamp.version,'.RiderStampDesignRegistry::StampSchemaVersion,
                'nullable',
                'integer',
                Rule::in([1]),
            ],
            'rider.stamp.artwork_source' => ['nullable', Rule::enum(RiderStampArtworkSource::class)],
            'rider.stamp.artwork_treatment' => ['nullable', Rule::enum(RiderStampArtworkTreatment::class)],
            'rider.stamp.copy_source' => ['nullable', Rule::enum(RiderStampCopySource::class)],
            'rider.stamp.show_logo' => ['nullable', 'boolean'],
            'rider.stamp.show_tagline' => ['nullable', 'boolean'],
            'rider.stamp.claim_marker' => ['nullable', Rule::enum(RiderStampClaimMarker::class)],
            'rider.stamp.claim_marker_position' => ['nullable', Rule::enum(RiderStampClaimMarkerPosition::class)],

            'count' => ['nullable', 'integer', 'min:1'],
            'provider' => ['nullable', 'string', 'max:80'],
            'prefix' => ['nullable', 'string'],
            'mask' => ['nullable', 'string'],
            'ttl' => ['nullable'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'voucher_type' => ['nullable', 'string', 'max:80'],
            'target_amount' => ['nullable', 'numeric', 'min:0'],
            'rules' => ['nullable', 'array'],
            'rules.auto_close_on_full_payment' => ['nullable', 'boolean'],
            'onboarding' => ['nullable', 'boolean'],
            'validation' => ['nullable', 'array'],
            'validation.*' => ['nullable'],
            'execution' => ['nullable', 'array'],
            'execution.schema' => ['nullable', 'string', 'max:120'],
            'execution.driver' => ['nullable', 'string', 'max:120'],
            'execution.mode' => ['nullable', 'string', 'max:120'],
            'execution.pipeline' => ['nullable', 'array'],
            'execution.pipeline.*' => ['string', 'max:120'],
            'execution.fallback' => ['nullable', 'string', 'max:120'],
            'execution.visibility' => ['nullable', 'array'],
            'execution.metadata' => ['nullable', 'array'],
            'stored_value' => [
                'nullable',
                'array:enabled,replenishable,maximum_balance,otp_required_above',
            ],
            'stored_value.enabled' => ['required_with:stored_value', 'boolean'],
            'stored_value.replenishable' => [
                'required_if:stored_value.enabled,true',
                'boolean',
            ],
            'stored_value.maximum_balance' => [
                'required_if:stored_value.enabled,true',
                'numeric',
                'min:0.01',
            ],
            'stored_value.otp_required_above' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'claim' => ['nullable', 'array'],
            'claim.outcomes' => ['required_with:claim', 'array', 'min:1'],
            'claim.outcomes.*' => ['required', 'array'],
            'claim.outcomes.*.key' => [
                'required',
                'string',
                'regex:/^[a-z][a-z0-9_]*$/',
                'distinct:strict',
            ],
            'claim.outcomes.*.pricing_profile' => ['nullable', 'string', 'max:120'],
            'claim.outcomes.*.requirements' => ['nullable', 'array'],
            'claim.selection' => ['nullable', 'string', 'in:claimant,server'],
            'claim.consumption' => ['nullable', 'string', 'in:one_of'],
            'claim.default_outcome' => [
                'nullable',
                'string',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::in($this->declaredClaimOutcomeKeys()),
            ],
            'claim.onboarding' => ['nullable', 'array'],
            'claim.onboarding.mode' => ['nullable', 'string', 'in:never,if_required,required'],
            'claim.onboarding.profile' => ['nullable', 'string', 'max:120'],
            'claim.claimant' => ['nullable', 'array'],
            'claim.claimant.mode' => ['nullable', 'string', 'in:unbound,recipient'],
            'claim.claimant.reference' => [
                'nullable',
                'string',
                'max:255',
                'required_if:claim.claimant.mode,recipient',
                'prohibited_unless:claim.claimant.mode,recipient',
            ],
            'claim.profile' => ['nullable', 'string', 'in:voucher.claim.v1'],
            'slice_plan' => ['nullable', 'array'],
            'slice_plan.schema' => ['required_with:slice_plan', 'string', 'in:voucher.slice-plan.v1'],
            'slice_plan.mode' => ['required_with:slice_plan', 'string', 'in:equal,flexible,scheduled'],
            'slice_plan.selection' => ['required_with:slice_plan', 'string', 'in:next_only,one,one_or_many,flexible_amount'],
            'slice_plan.total_minor' => ['required_with:slice_plan', 'integer', 'min:1'],
            'slice_plan.currency' => ['required_with:slice_plan', 'string', 'size:3', 'uppercase'],
            'slice_plan.slices' => ['present_with:slice_plan', 'array'],
            'slice_plan.slices.*.id' => ['required', 'string', 'regex:/^[a-z][a-z0-9_-]{0,79}$/'],
            'slice_plan.slices.*.label' => ['required', 'string', 'min:1', 'max:120'],
            'slice_plan.slices.*.amount_minor' => ['required', 'integer', 'min:1'],
            'slice_plan.slices.*.sequence' => ['required', 'integer', 'min:1'],
            'slice_plan.slices.*.claim_on' => ['nullable', 'date'],
            'slice_plan.slices.*.claim_by' => ['nullable', 'date'],
            'slice_plan.max_slices' => ['nullable', 'integer', 'min:1'],
            'slice_plan.min_amount_minor' => ['nullable', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
            'metadata.campaign' => ['nullable', 'array'],
            'metadata.campaign.planning_key' => ['nullable', 'string', 'max:120'],
            'metadata.campaign.execution_id' => ['nullable', 'string', 'max:120'],
            'metadata.campaign.campaign_id' => ['nullable', 'string', 'max:120'],
            'metadata.campaign.audience_id' => ['nullable', 'string', 'max:120'],
            'metadata.campaign.recipient_id' => ['nullable', 'string', 'max:120'],
            'metadata.campaign.source' => ['nullable', 'string', 'max:80'],
            'metadata.slices' => ['nullable', 'array'],
            'metadata.slices.*' => ['required', 'array'],
            'metadata.slices.*.id' => ['nullable', 'string', 'max:80'],
            'metadata.slices.*.amount' => ['required_with:metadata.slices', 'numeric', 'min:0.01'],
            'metadata.slices.*.description' => ['nullable', 'string', 'max:255'],
            'metadata.slices.*.tag' => ['nullable', 'string', 'max:80'],
            'metadata.slices.*.claim_on' => ['nullable', 'date'],
            'metadata.slices.*.claim_by' => ['nullable', 'date'],
            'metadata.slices.*.metadata' => ['nullable', 'array'],
            'metadata.slice_policy' => ['nullable', 'array'],
            'metadata.slice_policy.mode' => ['nullable', 'string'],
            'metadata.slice_policy.selection' => ['nullable', 'string'],
            'metadata.slice_policy.enforced' => ['nullable', 'boolean'],
            'metadata.custom' => ['nullable', 'array'],
            'metadata.custom.cockpit' => ['nullable', 'array'],
            'metadata.custom.cockpit.template_key' => ['nullable', 'string', 'max:80'],
            'metadata.custom.cockpit.source' => ['nullable', 'string', 'max:80'],
            'metadata.custom.cockpit.payee' => ['nullable', 'array'],
            'metadata.custom.cockpit.payee.kind' => ['nullable', Rule::enum(CockpitPayeeKind::class)],
            'metadata.custom.cockpit.payee.explicit_secret' => ['nullable', 'boolean'],
            'metadata.custom.cockpit.purpose' => ['nullable', 'string', 'max:255'],
            'metadata.custom.cockpit.recipient_reference' => ['nullable', 'string', 'max:80'],
            'metadata.custom.settlement' => ['nullable', 'array'],
            'metadata.custom.settlement.destinations' => ['nullable', 'array'],
            'metadata.custom.settlement.destinations.*' => [
                'string',
                'in:provider_payout,account_funding',
            ],
            'metadata.custom.settlement.account_funding' => ['nullable', 'array'],
            'metadata.custom.settlement.account_funding.pricing_profile' => [
                'nullable',
                'string',
                'in:account-funding-v1',
            ],
            'metadata.custom.named_slices' => ['nullable', 'array'],
            'metadata.custom.named_slice_policy' => ['nullable', 'array'],

            '_pricing' => ['nullable', 'array:offering_reference,offering_version,offering_snapshot_hash,quote_reference'],
            '_pricing.offering_reference' => ['required_with:_pricing', 'string', 'max:190'],
            '_pricing.offering_version' => ['required_with:_pricing', 'integer', 'min:1'],
            '_pricing.offering_snapshot_hash' => ['required_with:_pricing', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            '_pricing.quote_reference' => ['nullable', 'string', 'max:190'],

            'issuer_id' => ['sometimes', 'integer'], // TODO: make this intentional, require this
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeSettlementRailForValidation();
        $this->sanitizeRiderSplashHtmlForValidation();
    }

    private function normalizeSettlementRailForValidation(): void
    {
        $cash = $this->input('cash', []);

        if (! is_array($cash) || ! is_string($cash['settlement_rail'] ?? null)) {
            return;
        }

        $cash['settlement_rail'] = strtoupper(trim($cash['settlement_rail']));

        $this->merge(['cash' => $cash]);
    }

    /**
     * @return list<string>
     */
    private function declaredClaimOutcomeKeys(): array
    {
        return collect($this->input('claim.outcomes', []))
            ->pluck('key')
            ->filter(static fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();
    }
}
