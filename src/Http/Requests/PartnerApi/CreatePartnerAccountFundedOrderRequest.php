<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\PartnerApi;

use Illuminate\Validation\Rule;

final class CreatePartnerAccountFundedOrderRequest extends IssuePartnerPayCodeRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'funding_method' => ['required', Rule::in(['qr_ph', 'bank_transfer'])],
            'payer_mobile' => ['required_if:funding_method,bank_transfer', 'nullable', 'string', 'max:32'],
            'count' => ['nullable', 'integer', 'in:1'],
        ];
    }
}
