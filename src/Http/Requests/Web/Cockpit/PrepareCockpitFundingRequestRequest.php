<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Services\Funding\FundingRequestAccess;

class PrepareCockpitFundingRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null && app(FundingRequestAccess::class)->isReviewer($actor);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => mb_strtoupper(trim((string) $this->input('currency', 'PHP'))),
            'connection_reference' => trim((string) $this->input('connection_reference')),
            'evidence_reference' => trim((string) $this->input('evidence_reference')),
            'review_notes' => trim((string) $this->input('review_notes')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recognized_value_minor' => ['required', 'integer', 'min:1', 'max:999999999999999'],
            'currency' => ['required', 'string', 'size:3'],
            'connection_reference' => ['required', 'string', 'max:191'],
            'evidence_reference' => ['required', 'string', 'max:191'],
            'review_notes' => ['nullable', 'string', 'max:4000'],
            'provider_transaction_id' => ['prohibited'],
            'provider_payload' => ['prohibited'],
        ];
    }
}
