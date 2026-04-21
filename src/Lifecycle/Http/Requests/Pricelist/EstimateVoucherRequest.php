<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Requests\Pricelist;

use Illuminate\Foundation\Http\FormRequest;

class EstimateVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // TODO: Mirror public voucher estimate payload.
        ];
    }
}
