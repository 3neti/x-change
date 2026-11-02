<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateContributionInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_types' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'document_types.*' => ['required', 'string', 'max:64', 'distinct'],
            'expires_days' => ['required', 'integer', 'min:1', 'max:90'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ];
    }
}
