<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Requests\Claims;

use Illuminate\Foundation\Http\FormRequest;

class SubmitVoucherClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // TODO: Mirror public claim-submit payload.
        ];
    }
}
