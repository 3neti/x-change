<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Redemption;

use Illuminate\Foundation\Http\FormRequest;

class LoadRedemptionCompletionContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference_id' => ['nullable', 'string'],
            'flow_id' => ['nullable', 'string'],
        ];
    }
}
