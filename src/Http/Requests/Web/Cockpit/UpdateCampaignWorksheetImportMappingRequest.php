<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignWorksheetImportMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $mapping = array_filter(
            (array) $this->input('mapping', []),
            fn (mixed $value): bool => is_string($value) && trim($value) !== '',
        );

        $this->merge([
            'mapping' => $mapping,
            'default_wallet' => trim((string) $this->input('default_wallet', 'GCash')),
            'default_delivery_preference' => mb_strtolower(trim((string) $this->input('default_delivery_preference', 'manual'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'mapping' => ['required', 'array'],
            'mapping.*' => ['string', 'max:160'],
            'default_wallet' => ['required', 'string', 'max:120'],
            'default_delivery_preference' => ['required', Rule::in(['manual', 'sms', 'email'])],
        ];
    }
}
