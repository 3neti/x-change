<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;

final class StoreCommercialPartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $operator = $this->user();

        return $operator instanceof Model
            && app(CommercialOperatorAuthorityContract::class)
                ->allows($operator, CommercialOperatorCapability::ManagePartners);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9][a-z0-9:._-]+$/'],
            'display_name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:190'],
            'external_reference' => ['nullable', 'string', 'max:190'],
            'attribution_basis' => ['required', 'string', 'max:100'],
            'authorization_reference' => ['required', 'string', 'max:190'],
            'terms' => ['nullable', 'array'],
            'terms.commission_basis' => ['nullable', 'string', 'max:100'],
            'terms.settlement_cycle' => ['nullable', 'string', 'max:100'],
        ];
    }
}
