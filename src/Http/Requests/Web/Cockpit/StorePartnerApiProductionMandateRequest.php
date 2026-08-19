<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LBHurtado\XChange\Enums\PartnerApiOperatorCapability;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiOperatorAuthority;

final class StorePartnerApiProductionMandateRequest extends FormRequest
{
    public function authorize(PartnerApiOperatorAuthority $authority): bool
    {
        return $this->user() instanceof Model
            && $authority->allows($this->user(), PartnerApiOperatorCapability::RequestProductionClients);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'issuer_id' => ['required', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['required', 'string', Rule::in(array_keys((array) config('x-change.partner_api.scopes', [])))],
            'currencies' => ['required', 'array', 'size:1'],
            'currencies.*' => ['required', Rule::in(['PHP'])],
            'settlement_rails' => ['required', 'array', 'min:1'],
            'settlement_rails.*' => ['required', Rule::in(['automatic', 'INSTAPAY', 'PESONET'])],
            'maximum_amount_minor' => ['required', 'integer', 'min:1'],
            'daily_principal_limit_minor' => ['required', 'integer', 'gte:maximum_amount_minor'],
            'unbound_pay_codes' => ['required', 'boolean'],
            'voucher_profiles' => ['sometimes', 'array', 'min:1'],
            'voucher_profiles.*' => ['required', 'string', Rule::in(['disbursement', 'stored_value'])],
            'stored_value_spend' => ['sometimes', 'array'],
            'stored_value_spend.enabled' => ['nullable', 'boolean'],
            'stored_value_spend.currencies' => ['nullable', 'array', 'size:1'],
            'stored_value_spend.currencies.*' => ['required', Rule::in(['PHP'])],
            'stored_value_spend.maximum_amount_minor' => ['nullable', 'integer', 'min:1'],
            'stored_value_spend.daily_amount_minor' => ['nullable', 'integer', 'gte:stored_value_spend.maximum_amount_minor'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $scopes = array_values((array) $this->input('scopes', []));
            $usesStoredValue = array_intersect($scopes, ['stored-value:read', 'stored-value:spend']) !== [];
            $enabled = $this->boolean('stored_value_spend.enabled');

            if ($usesStoredValue && ! $enabled) {
                $validator->errors()->add(
                    'stored_value_spend.enabled',
                    'Stored-value scopes require an explicitly enabled reusable-balance mandate.',
                );
            }

            if ($usesStoredValue && ! is_array($this->input('stored_value_spend.currencies'))) {
                $validator->errors()->add(
                    'stored_value_spend.currencies',
                    'Stored-value scopes require an explicit currency.',
                );
            }

            if (in_array('stored-value:spend', $scopes, true)) {
                if (! is_numeric($this->input('stored_value_spend.maximum_amount_minor'))) {
                    $validator->errors()->add(
                        'stored_value_spend.maximum_amount_minor',
                        'Enter a positive maximum amount for each stored-value spend.',
                    );
                }

                if (! is_numeric($this->input('stored_value_spend.daily_amount_minor'))) {
                    $validator->errors()->add(
                        'stored_value_spend.daily_amount_minor',
                        'Enter a daily stored-value spend limit.',
                    );
                }
            }

            if (! $usesStoredValue && $enabled) {
                $validator->errors()->add(
                    'stored_value_spend.enabled',
                    'Select a stored-value scope before enabling its mandate.',
                );
            }
        }];
    }
}
