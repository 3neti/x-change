<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UploadParticipantDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', 'max:64'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
