<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;

final class StoreCommercialPartnerDestinationRequest extends FormRequest
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
            'provider' => ['required', 'string', 'max:100'],
            'connection_reference' => ['required', 'string', 'max:190'],
            'currency' => ['required', 'string', 'size:3'],
            'bank_code' => ['required', 'string', 'max:32'],
            'account_number' => ['required', 'string', 'max:64'],
            'recipient_name' => ['required', 'string', 'max:190'],
            'mobile' => ['required', 'string', 'max:32'],
            'authorization_reference' => ['required', 'string', 'max:190'],
        ];
    }
}
