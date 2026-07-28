<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LBHurtado\Voucher\Data\RiderStampData;
use LBHurtado\Voucher\Enums\RiderContentFormat;
use LBHurtado\Voucher\Enums\RiderStampFit;
use LBHurtado\Voucher\Enums\RiderStampPosition;
use LBHurtado\Voucher\Enums\RiderStampSource;
use LBHurtado\Voucher\Enums\RiderStampTheme;
use LBHurtado\XChange\Http\Requests\Concerns\SanitizesRiderSplashHtml;
use LBHurtado\XChange\Http\Requests\Concerns\ValidatesMinimumWithdrawalPolicy;

class EstimatePayCodeRequest extends FormRequest
{
    use SanitizesRiderSplashHtml;
    use ValidatesMinimumWithdrawalPolicy;

    public function authorize(): bool
    {
        return true;
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
            'cash.settlement_rail' => ['nullable', 'string', 'max:50'],
            'cash.slice_mode' => ['nullable', 'string', 'in:fixed,open'],
            'cash.slices' => ['nullable', 'integer', 'min:1'],
            'cash.max_slices' => ['nullable', 'integer', 'min:1'],
            'cash.min_withdrawal' => ['nullable', 'numeric', 'min:0'],

            'cash.validation' => ['nullable', 'array'],
            'cash.validation.secret' => ['nullable'],
            'cash.validation.mobile' => ['nullable', 'string'],
            'cash.validation.payable' => ['nullable', 'string'],
            'cash.validation.country' => ['nullable', 'string', 'max:10'],
            'cash.validation.location' => ['nullable', 'string'],
            'cash.validation.radius' => ['nullable', 'string'],

            'inputs' => ['required', 'array'],
            'inputs.fields' => ['present', 'array'],

            'feedback' => ['required', 'array'],
            'feedback.email' => ['nullable', 'email'],
            'feedback.mobile' => ['nullable', 'string'],
            'feedback.webhook' => ['nullable', 'url'],

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
            'rider.stamp.version' => ['nullable', 'integer', Rule::in([RiderStampData::SCHEMA_VERSION])],

            'count' => ['nullable', 'integer', 'min:1'],
            'provider' => ['nullable', 'string', 'max:80'],
            'prefix' => ['nullable', 'string'],
            'mask' => ['nullable', 'string'],
            'ttl' => ['nullable'],
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
            'metadata' => ['nullable', 'array'],
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->sanitizeRiderSplashHtmlForValidation();
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
