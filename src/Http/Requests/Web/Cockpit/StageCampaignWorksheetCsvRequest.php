<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StageCampaignWorksheetCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:5120',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile
                        || ! in_array(mb_strtolower((string) $value->getClientOriginalExtension()), ['csv', 'txt', 'xlsx'], true)) {
                        $fail('Upload a CSV or XLSX worksheet file.');
                    }
                },
            ],
        ];
    }
}
