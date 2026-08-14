<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LBHurtado\Voucher\Enums\RiderContentFormat;
use LBHurtado\XChange\Enums\RiderLibraryEntryKind;
use LBHurtado\XRider\Support\RiderHtmlSanitizer;

class StoreRiderLibraryEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Model;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->input('payload', []);

        if (! is_array($payload)) {
            return;
        }

        $format = strtolower(trim((string) ($payload['format'] ?? 'plain')));

        if (
            $this->input('kind') === RiderLibraryEntryKind::Splash->value
            && $format === RiderContentFormat::Html->value
            && is_string($payload['splash'] ?? null)
        ) {
            $payload['splash'] = app(RiderHtmlSanitizer::class)
                ->sanitizeSplash($payload['splash']);
            $payload['format'] = RiderContentFormat::Html->value;

            $this->merge(['payload' => $payload]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isUrl = $this->input('kind') === RiderLibraryEntryKind::Url->value;
        $isSplash = $this->input('kind') === RiderLibraryEntryKind::Splash->value;

        return [
            'kind' => ['required', Rule::enum(RiderLibraryEntryKind::class)],
            'label' => ['nullable', 'string', 'max:80'],
            'payload' => ['required', 'array'],
            'payload.url' => [
                Rule::requiredIf($isUrl),
                Rule::prohibitedIf($isSplash),
                'nullable',
                'url:http,https',
                'max:2048',
            ],
            'payload.splash' => [
                Rule::requiredIf($isSplash),
                Rule::prohibitedIf($isUrl),
                'nullable',
                'string',
                'max:51200',
            ],
            'payload.format' => [
                Rule::requiredIf($isSplash),
                Rule::prohibitedIf($isUrl),
                'nullable',
                Rule::enum(RiderContentFormat::class),
            ],
        ];
    }
}
