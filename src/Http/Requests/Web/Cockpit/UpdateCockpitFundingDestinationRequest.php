<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCockpitFundingDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mode' => strtolower(trim((string) $this->input('mode'))),
            'enrollment' => strtolower(trim((string) $this->input('enrollment'))),
            'account_number' => preg_replace('/\D+/', '', (string) $this->input('account_number')),
            'account_name' => trim((string) $this->input('account_name')),
            'vca_alias' => preg_replace('/\D+/', '', (string) $this->input('vca_alias')),
            'vca_alias_token' => trim((string) $this->input('vca_alias_token')),
            'wallet_id' => strtoupper(trim((string) $this->input('wallet_id'))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $provider = $this->provider();
        $dedicated = $this->input('mode') === 'dedicated';

        return [
            'mode' => ['required', 'string', Rule::in(['shared', 'dedicated'])],
            'enrollment' => [
                Rule::requiredIf($provider === 'netbank' && $dedicated),
                'nullable',
                'string',
                Rule::in(['generate', 'import']),
            ],
            'account_number' => [
                Rule::requiredIf($provider === 'netbank' && $dedicated),
                'nullable',
                'regex:/^\d{8,32}$/',
            ],
            'account_name' => [
                Rule::requiredIf($provider === 'netbank' && $dedicated),
                'nullable',
                'string',
                'max:191',
            ],
            'vca_alias' => [
                Rule::requiredIf($provider === 'netbank' && $dedicated),
                'nullable',
                'regex:/^\d{5}$/',
            ],
            'vca_alias_token' => [
                Rule::requiredIf(
                    $provider === 'netbank'
                    && $dedicated
                    && $this->input('enrollment') === 'import',
                ),
                'nullable',
                'string',
                'max:4096',
            ],
            'wallet_id' => [
                Rule::requiredIf($provider === 'paynamics_constellation' && $dedicated),
                'nullable',
                'string',
                'max:64',
                'regex:/^[A-Z0-9_-]+$/',
            ],
        ];
    }

    public function provider(): string
    {
        return match (strtolower((string) $this->route('provider'))) {
            'paynamics' => 'paynamics_constellation',
            default => strtolower((string) $this->route('provider')),
        };
    }
}
