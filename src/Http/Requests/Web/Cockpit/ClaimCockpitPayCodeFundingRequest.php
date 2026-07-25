<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;

final class ClaimCockpitPayCodeFundingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'inspection_token' => [
                'required',
                'string',
                'size:64',
                'alpha_num:ascii',
            ],
        ];
    }
}
