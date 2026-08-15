<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
        ];
    }
}
