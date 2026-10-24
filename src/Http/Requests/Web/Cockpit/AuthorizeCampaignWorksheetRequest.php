<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuthorizeCampaignWorksheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'approval_mode' => $this->input('approval_mode', 'direct'),
        ]);
    }

    public function rules(): array
    {
        return [
            'approval_mode' => ['required', Rule::in(['direct', 'maker_checker'])],
        ];
    }
}
