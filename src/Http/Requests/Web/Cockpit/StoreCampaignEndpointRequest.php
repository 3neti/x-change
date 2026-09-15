<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $usageKeys = array_keys((array) config('x-change.campaigns.usage_profiles', []));
        $capabilityKeys = array_keys((array) config('x-change.campaigns.endpoint_capabilities', []));

        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:240'],
            'usage_key' => ['required', 'string', Rule::in($usageKeys)],
            'capabilities' => ['array', 'max:8'],
            'capabilities.*' => ['string', Rule::in($capabilityKeys)],
            'pay_code_template_id' => ['required', 'integer'],
            'endpoint_slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'starts_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'budget_cap_minor' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => [
                'nullable',
                'date',
                Rule::when($this->filled('starts_at'), ['after:starts_at']),
            ],
            'daily_window_start' => ['nullable', 'date_format:H:i'],
            'daily_window_end' => ['nullable', 'date_format:H:i'],
            'timezone' => ['nullable', 'timezone'],
        ];
    }
}
