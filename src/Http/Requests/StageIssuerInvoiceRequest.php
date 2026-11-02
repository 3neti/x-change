<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class StageIssuerInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $types = 'pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg';

        return ['invoice' => ['required', 'file', 'mimes:'.$types, 'extensions:'.$types, 'max:'.max(1, min(10240, (int) config('x-change.documents.issuer_max_kilobytes', 10240))),
            function (string $attribute, mixed $file, \Closure $fail): void {
                if (! $file instanceof UploadedFile) {
                    return;
                }
                $allowed = [
                    'pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
                    'doc' => ['application/msword', 'application/x-ole-storage'],
                    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                    'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage'],
                    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                    'csv' => ['text/csv', 'text/plain'],
                ];
                $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
                if (! in_array($detectedMime, $allowed[strtolower($file->getClientOriginalExtension())] ?? [], true)) {
                    $fail('The document content does not match its file type.');
                }
            },
        ]];
    }
}
