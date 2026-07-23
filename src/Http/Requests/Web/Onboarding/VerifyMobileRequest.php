<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyMobileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ];
    }
}
