<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCockpitFundingIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => strtolower(trim((string) $this->input('provider'))),
            'currency' => strtoupper(trim((string) $this->input('currency', 'PHP'))),
            'idempotency_key' => trim((string) $this->input('idempotency_key')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in($this->enabledProviders())],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'currency' => ['required', 'string', 'size:3'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ];
    }

    /**
     * @return list<string>
     */
    private function enabledProviders(): array
    {
        return collect((array) config('x-change.funding.providers', []))
            ->filter(fn (mixed $settings): bool => (bool) data_get($settings, 'enabled', false))
            ->keys()
            ->map(fn (mixed $provider): string => (string) $provider)
            ->values()
            ->all();
    }
}
