<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;

final class ApproveCommercialPartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $operator = $this->user();

        return $operator instanceof Model
            && app(CommercialOperatorAuthorityContract::class)
                ->allows($operator, CommercialOperatorCapability::ApprovePartners);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
