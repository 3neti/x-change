<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReviewEnvelopeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:accepted,rejected'],
            'reason' => ['nullable', 'string', 'max:1000', 'required_if:decision,rejected'],
        ];
    }
}
