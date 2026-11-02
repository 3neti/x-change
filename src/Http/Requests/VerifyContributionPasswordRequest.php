<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyContributionPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'uuid'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
